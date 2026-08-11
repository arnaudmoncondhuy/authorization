<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Kernel;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Bridge\DoctorCommand;
use ArnaudMoncondhuy\Authorization\Bridge\MissingPermissionListener;
use ArnaudMoncondhuy\Authorization\Bridge\PermissionsCommand;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Scope\SystemIdentity;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Rend interrogeables, pour les seuls tests, les services que le paquet déclare privés.
 *
 * Sans quoi ils ne survivraient pas à la compilation : un service privé que personne n'injecte
 * est retiré du conteneur. C'est le comportement normal, et il vaut aussi pour une
 * application — l'inventaire des droits n'existe que si son écran d'administration le réclame.
 */
final class ExposeForTestingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $services = [
            PermissionCatalog::class,
            MissingPermissionListener::class,
            SystemIdentity::class,
            DoctorCommand::class,
            PermissionsCommand::class,
        ];

        foreach ($services as $service) {
            if ($container->hasDefinition($service)) {
                $container->getDefinition($service)->setPublic(true);
            }
        }

        if ($container->hasAlias(Authorizer::class)) {
            $container->getAlias(Authorizer::class)->setPublic(true);
        }
    }
}
