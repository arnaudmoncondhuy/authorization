<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\DependencyInjection;

use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
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
    public function process(ContainerBuilder $container): void
    {
        /** @var array<string, Permission> $collected */
        $collected = [];
        $collisions = [];

        foreach (array_keys($container->findTaggedServiceIds(Tag::USE_CASE)) as $service) {
            $class = $container->findDefinition($service)->getClass() ?? $service;
            $reflection = $container->getReflectionClass($class, false);

            if (!$reflection instanceof \ReflectionClass || !$reflection->implementsInterface(UseCase::class)) {
                continue;
            }

            foreach ($reflection->getAttributes(RequiresPermission::class) as $attribute) {
                $permission = $attribute->newInstance()->permission;
                $id = $permission->id();

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

        $container->register(PermissionCatalog::class, PermissionCatalog::class)
            ->setArguments([array_values($collected)]);
    }

    private static function name(Permission $permission): string
    {
        return $permission instanceof \UnitEnum
            ? $permission::class.'::'.$permission->name
            : $permission::class;
    }
}
