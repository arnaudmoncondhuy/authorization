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
 *
 * Ce qu'elle ne saute plus en silence : un fichier `.php` dont le chemin n'annonce aucun type
 * connu. Il est rapporté comme faute, parce qu'un cas d'usage mal logé y échapperait à tout.
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
        $unreadable = [];

        foreach (self::classesUnder($directory, $namespacePrefix, $unreadable) as $class) {
            $reflection = new \ReflectionClass($class);
            $declared = $reflection->getAttributes(RequiresPermission::class);

            if ($reflection->isTrait()) {
                // Le corps d'un trait est déjà lu à travers la classe qui l'emploie :
                // `ReflectionMethod::getDeclaringClass()` y rend celle qui l'utilise, pas le
                // trait. Le relire ici accuserait deux fois la même ligne, et la seconde à
                // tort. Reste l'attribut, qui lui ne se propage pas : posé sur un trait, il
                // n'est lu par personne — ni par les passes, qui ne voient que des services,
                // ni par le contrôle ci-dessous, qui interroge la classe.
                if ([] !== $declared) {
                    $faults[] = $reflection->getShortName().' déclare un droit sur un trait, où l\'attribut n\'est lu par personne';
                }

                continue;
            }

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

            $body = self::executableCode(self::bodiesOf($reflection));

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

        // Ajoutées à la fin mais triées avec le reste : un fichier que la lecture n'a pas su
        // ouvrir est une faute au même titre, et de la pire espèce — c'est celle qui laisse
        // croire que tout a été regardé.
        $faults = array_merge($faults, $unreadable);

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
        $source = false !== $file ? self::executableCode((string) file_get_contents($file)) : '';

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
     * Le code qui s'exécute, débarrassé de ce qui ne s'exécute pas.
     *
     * Sans cela, une garde **mise en commentaire** serait comptée comme une réclamation :
     * le texte cherché y figure en toutes lettres, et c'est le geste le plus banal d'un
     * débogage. Le verbe tournerait alors sans arbitrage pendant que ce contrôle certifie
     * l'inverse — la faute la plus dangereuse qu'il puisse commettre, puisqu'elle ouvre.
     *
     * Les chaînes littérales et les heredocs partent pour la même raison.
     */
    private static function executableCode(string $source): string
    {
        $code = '';

        foreach (\PhpToken::tokenize('<?php '.$source) as $token) {
            if ($token->is([\T_COMMENT, \T_DOC_COMMENT, \T_CONSTANT_ENCAPSED_STRING, \T_ENCAPSED_AND_WHITESPACE, \T_INLINE_HTML])) {
                continue;
            }

            $code .= $token->text;
        }

        return $code;
    }

    /**
     * Toutes les méthodes que la classe déclare, `__invoke()` compris.
     *
     * Pas seulement `__invoke()` : un cas d'usage qui modifie plusieurs champs réclame le
     * droit fin **au moment précis où le champ change**, donc dans la méthode qui l'applique.
     * Ne lire que l'entrée accuserait ce motif — parfaitement légitime, et le plus fin qui
     * soit — de déclarer des droits qu'il ne réclame jamais.
     *
     * La classe entière reste le cas d'usage : la garantie visée, « ce que l'attribut déclare,
     * le corps le réclame », est intacte.
     *
     * @param \ReflectionClass<object> $reflection
     */
    private static function bodiesOf(\ReflectionClass $reflection): string
    {
        $bodies = '';

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $reflection->getName()) {
                $bodies .= self::bodyOf($method)."\n";
            }
        }

        return $bodies;
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
        return new \ReflectionClass($permission)->getShortName().'::'.$permission->name;
    }

    /**
     * Les types que porte cette arborescence, déduits des chemins selon PSR-4.
     *
     * Le fichier est lu avant d'être chargé, et c'est l'ordre qui compte. `class_exists()`
     * déclenche l'autoloader, qui inclut le fichier que le chemin désigne : sur un fichier qui
     * ne déclare pas ce que sa place annonce, l'inclusion a bien lieu, la classe n'apparaît
     * pas, et rien n'empêche de recommencer à l'appel suivant — d'où un « Cannot redeclare »
     * sur la deuxième lecture d'une même arborescence. Un fichier de script égaré serait, lui,
     * purement et simplement exécuté par un contrôle censé se contenter de lire.
     *
     * On regarde donc d'abord ce que le fichier déclare, et on ne charge que ce qui tient sa
     * promesse.
     *
     * Ce qu'aucun mot-clé ne déclare est rapporté au lieu d'être sauté. Un cas d'usage logé
     * dans un fichier que sa place ne nomme pas échappait à toute lecture sans une ligne pour
     * le dire : le seul saut de ce contrôle qui ouvre au lieu de bruiter.
     *
     * @param non-empty-string $namespacePrefix
     * @param list<string>     $unreadable      recueille les fichiers dont le chemin ne mène à aucun type
     *
     * @return list<class-string>
     */
    private static function classesUnder(string $directory, string $namespacePrefix, array &$unreadable): array
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

            if (\in_array($class, self::typesDeclaredIn($file->getPathname()), true)) {
                $classes[] = $class;
                continue;
            }

            $unreadable[] = \sprintf(
                '%s n\'a pas été examiné : son chemin annonce %s, que ce fichier ne déclare pas',
                $relative,
                $class,
            );
        }

        sort($classes);

        return $classes;
    }

    /**
     * Les types pleinement qualifiés qu'un fichier déclare, lus sans le charger.
     *
     * Les quatre mots-clés sont cherchés, pas seulement `class` : `class_exists()` rend faux
     * pour une interface comme pour un trait, et s'en remettre à lui écartait en silence tout
     * ce que ces deux mots déclarent — un attribut posé sur une interface n'était donc lu par
     * personne. Une énumération, elle, est bien une classe, mais son mot-clé est distinct.
     *
     * @return list<class-string>
     */
    private static function typesDeclaredIn(string $file): array
    {
        $tokens = \PhpToken::tokenize((string) file_get_contents($file));
        $meaningful = array_values(array_filter(
            $tokens,
            static fn (\PhpToken $t): bool => !$t->is([\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT]),
        ));

        $namespace = '';
        $types = [];

        foreach ($meaningful as $index => $token) {
            $next = $meaningful[$index + 1] ?? null;

            if ($token->is(\T_NAMESPACE) && null !== $next && $next->is([\T_STRING, \T_NAME_QUALIFIED])) {
                $namespace = $next->text.'\\';
                continue;
            }

            if (!$token->is([\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM])) {
                continue;
            }

            // `Foo::class` porte le mot-clé sans rien déclarer, et `new class {}` ne le fait
            // suivre d'aucun nom.
            $previous = $meaningful[$index - 1] ?? null;

            if ((null !== $previous && $previous->is(\T_DOUBLE_COLON)) || null === $next || !$next->is(\T_STRING)) {
                continue;
            }

            /** @var class-string $declared */
            $declared = $namespace.$next->text;
            $types[] = $declared;
        }

        return $types;
    }
}
