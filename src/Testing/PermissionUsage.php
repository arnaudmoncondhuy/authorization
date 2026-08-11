<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Testing;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Confronte ce qu'un cas d'usage déclare à ce que son corps réclame.
 *
 * L'attribut déclare, le corps applique, et rien dans le conteneur ne peut rapprocher les
 * deux : une passe de compilation lit des classes, jamais des corps de méthode. C'est le seul
 * contrôle du paquet qui ne peut pas arrêter la compilation — il se joue en test.
 *
 * L'application l'appelle sur son propre code :
 *
 *     $violations = PermissionUsage::violationsUnder(__DIR__.'/../src', 'App\\');
 *     self::assertSame([], $violations);
 *
 * Elle rend une liste et n'assertionne pas : ce paquet ne dépend d'aucun cadre de test, et
 * n'impose pas le sien.
 *
 * Sur un cas d'usage, la lecture se borne au corps de `__invoke()`, et cherche l'appel et non
 * le seul nom du droit. Lire le fichier entier ne prouverait rien — la ligne de déclaration
 * porte déjà le texte cherché ; chercher le nom seul n'en prouve pas beaucoup plus, puisque le
 * tester sans agir dessus s'écrit avec les mêmes caractères à un mot près.
 *
 * Les deux sens sont vérifiés. Un droit déclaré et jamais exigé fait apparaître une case que
 * rien n'applique ; un droit exigé et jamais déclaré n'entre dans aucun inventaire, ne peut
 * donc être accordé à personne, et ferme le verbe pour tout le monde — administrateur
 * compris, sans recours depuis l'application.
 *
 * Sur tout le reste, la lecture porte sur le fichier entier, et cherche une seule chose : un
 * droit réclamé hors d'un cas d'usage. C'est ce qui rattrape la faiblesse des trois contrôles
 * de compilation, qui ne jugent que ce qui implémente {@see UseCase} — une classe qui oublie
 * l'interface leur échappe tout en portant du métier gouverné par rien.
 *
 * Ce qu'elle ne voit pas : un droit exigé par une valeur calculée plutôt que par son cas
 * écrit en toutes lettres, et une énumération importée sous un autre nom.
 */
final class PermissionUsage
{
    /**
     * @param non-empty-string $namespacePrefix terminé par une contre-oblique
     *
     * @return list<string>
     */
    public static function violationsUnder(string $directory, string $namespacePrefix): array
    {
        $faults = [];

        foreach (self::classesUnder($directory, $namespacePrefix) as $class) {
            $reflection = new \ReflectionClass($class);
            $declared = $reflection->getAttributes(RequiresPermission::class);
            $isUseCase = !$reflection->isInterface() && $reflection->implementsInterface(UseCase::class);

            if (!$isUseCase) {
                $faults = array_merge($faults, self::faultsOutsideAUseCase($reflection, [] !== $declared));
                continue;
            }

            if ([] === $declared) {
                continue;
            }

            if (!$reflection->hasMethod('__invoke')) {
                $faults[] = $reflection->getShortName().' n\'expose pas __invoke()';
                continue;
            }

            $body = self::bodyOf($reflection->getMethod('__invoke'));

            // Un droit choisi par une valeur ne peut être rapproché de rien. Le dire plutôt
            // que d'accuser la classe de ne jamais réclamer ce qu'elle réclame peut-être :
            // l'accusation serait fausse, et elle enverrait chercher au mauvais endroit.
            if (1 === preg_match('/->require\(\s*\$/', $body)) {
                $faults[] = $reflection->getShortName().' réclame un droit par une valeur, que ce contrôle ne sait pas rapprocher de ses déclarations';
                continue;
            }

            $demanded = self::demandedIn($body);
            $written = [];

            foreach ($declared as $attribute) {
                $permission = $attribute->newInstance()->permission;
                $written[] = self::asWritten($permission);

                if (!\in_array(self::asWritten($permission), $demanded, true)) {
                    $faults[] = \sprintf('%s déclare %s sans jamais le réclamer', $reflection->getShortName(), $permission->id());
                }
            }

            foreach (array_diff($demanded, $written) as $extra) {
                $faults[] = \sprintf('%s réclame %s sans l\'avoir déclaré', $reflection->getShortName(), $extra);
            }
        }

        sort($faults);

        return $faults;
    }

    /**
     * Ce qu'une classe qui n'est pas un cas d'usage n'a pas le droit de faire.
     *
     * Deux fautes, et la seconde répare une faiblesse de tout le dispositif : les trois
     * contrôles de compilation ne jugent que ce qui implémente {@see UseCase}. Une classe de
     * la couche des cas d'usage qui oublierait cette interface échapperait à tout, tout en
     * réclamant des droits — donc en portant du métier gouverné par rien.
     *
     * Une implémentation du contrat est laissée tranquille : elle porte `require()`, elle ne
     * l'usurpe pas.
     *
     * @param \ReflectionClass<object> $reflection
     *
     * @return list<string>
     */
    private static function faultsOutsideAUseCase(\ReflectionClass $reflection, bool $declares): array
    {
        // Le conteneur ne voit que les services : une entité, un message ou un objet du
        // domaine porteraient l'attribut sans qu'aucune passe ne s'en aperçoive.
        if ($declares) {
            return [$reflection->getShortName().' déclare un droit sans être un cas d\'usage'];
        }

        if ($reflection->implementsInterface(Authorizer::class)) {
            return [];
        }

        $file = $reflection->getFileName();
        $source = false !== $file ? (string) file_get_contents($file) : '';

        if ([] === self::demandedIn($source)) {
            return [];
        }

        return [$reflection->getShortName().' réclame un droit sans être un cas d\'usage'];
    }

    /**
     * Les droits que le corps exige réellement. C'est l'appel qui est cherché, pas le nom du
     * droit : le tester sans agir dessus porte le même nom à un mot près.
     *
     * @return list<string>
     */
    private static function demandedIn(string $body): array
    {
        preg_match_all('/->require\(\s*([A-Za-z_][A-Za-z0-9_]*::[A-Za-z_][A-Za-z0-9_]*)\s*\)/', $body, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Le corps seul : la première ligne est celle de la signature, et les attributs de la
     * classe vivent bien au-dessus.
     */
    private static function bodyOf(\ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        $lines = false !== $file ? file($file) : false;

        if (false === $lines) {
            return '';
        }

        return implode('', \array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }

    /** Tel qu'un corps l'écrit : le nom court de l'énumération et celui du cas. */
    private static function asWritten(Permission $permission): string
    {
        if (!$permission instanceof \UnitEnum) {
            return $permission::class;
        }

        return new \ReflectionClass($permission)->getShortName().'::'.$permission->name;
    }

    /**
     * @param non-empty-string $namespacePrefix
     *
     * @return list<class-string>
     */
    private static function classesUnder(string $directory, string $namespacePrefix): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $root = rtrim($directory, '/');
        $classes = [];

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen($root) + 1);
            /** @var class-string $class */
            $class = $namespacePrefix.str_replace(['/', '.php'], ['\\', ''], $relative);

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }
}
