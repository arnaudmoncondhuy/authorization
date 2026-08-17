<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\DependencyInjection;

use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Proof;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\UseCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Recense les droits exigés par les cas d'usage et en garnit {@see PermissionCatalog}.
 *
 * Le recensement se fait à la compilation du conteneur, à partir des mêmes déclarations que
 * le contrôle : la liste ne peut donc être ni incomplète, ni périmée. Un conteneur recompilé
 * suffit à la rafraîchir, et il l'est à chaque déploiement.
 *
 * Deux droits distincts qui porteraient la même identité arrêtent la compilation. C'est le
 * seul rempart contre une collision, et ce qu'elle coûterait est lourd : deux verbes
 * partageraient un droit sans que rien ne le dise, et l'accorder pour l'un l'accorderait pour
 * l'autre.
 *
 * La collision se juge sur la valeur, pas sur la classe : elle guette autant entre deux
 * énumérations qu'entre deux cas d'une seule.
 */
final readonly class RegisterPermissionCatalogPass implements CompilerPassInterface
{
    /**
     * Les droits qui exigent une preuve d'identité, et laquelle : `['secret.reveal' =>
     * 'recent']`. Posé même vide, pour que {@see RefuseProofWithoutJudgePass} distingue « rien
     * n'exige de preuve » de « la collecte n'a pas eu lieu ».
     */
    public const string REQUIRED_PROOFS_PARAMETER = 'authorization.required_proofs';

    public function process(ContainerBuilder $container): void
    {
        /** @var array<string, Permission> $collected */
        $collected = [];
        /** @var array<string, Proof> $proofs */
        $proofs = [];
        $collisions = [];

        foreach (array_keys($container->findTaggedServiceIds(Tag::USE_CASE)) as $service) {
            $class = $container->findDefinition($service)->getClass() ?? $service;
            $reflection = $container->getReflectionClass($class, false);

            if (!$reflection instanceof \ReflectionClass || !$reflection->implementsInterface(UseCase::class)) {
                continue;
            }

            foreach ($reflection->getAttributes(RequiresPermission::class) as $attribute) {
                $declaration = $attribute->newInstance();
                $permission = $declaration->permission;
                $id = $permission->id();

                // Le plus exigeant l'emporte, sans que ce soit une faute à signaler : deux
                // cas d'usage peuvent légitimement porter le même droit et ne pas courir le
                // même risque. Retenir le plus faible ferait d'un cas d'usage ajouté demain
                // l'affaiblissement silencieux d'un droit déjà protégé.
                $proofs[$id] = Proof::strongest($proofs[$id] ?? Proof::None, $declaration->proof);

                // Comparés par valeur et non par classe : deux cas d'une même énumération qui
                // rendraient la même identité désigneraient deux droits distincts sous un seul
                // nom, et l'un écraserait l'autre dans l'inventaire — accorder ce nom
                // ouvrirait alors les deux verbes.
                if (isset($collected[$id]) && $collected[$id] != $permission) {
                    $collisions[$id] = \sprintf('%s partagée par %s et %s', $id, self::name($collected[$id]), self::name($permission));
                }

                $collected[$id] = $permission;
            }
        }

        if ([] !== $collisions) {
            throw new LogicException(\sprintf("Deux droits distincts portent la même identité :\n  - %s", implode("\n  - ", $collisions)));
        }

        ksort($collected);

        $required = array_filter($proofs, static fn (Proof $proof): bool => Proof::None !== $proof);
        ksort($required);

        $container->register(PermissionCatalog::class, PermissionCatalog::class)
            ->setArguments([array_values($collected), $required]);

        // La liste, en valeurs plutôt qu'en cas d'énumération : elle se lit depuis un conteneur
        // compilé, et c'est elle que RefuseProofWithoutJudgePass interroge pour savoir s'il
        // faut un juge. Toujours définie, vide comprise — son absence signifierait que la
        // passe n'a pas tourné, ce qui ne se distingue pas de « rien n'exige de preuve ».
        $container->setParameter(
            self::REQUIRED_PROOFS_PARAMETER,
            array_map(static fn (Proof $proof): string => $proof->value, $required),
        );
    }

    private static function name(Permission $permission): string
    {
        return $permission::class.'::'.$permission->name;
    }
}
