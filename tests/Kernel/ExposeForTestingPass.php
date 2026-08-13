<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Kernel;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Bridge\DoctorCommand;
use ArnaudMoncondhuy\Authorization\Bridge\MissingPermissionListener;
use ArnaudMoncondhuy\Authorization\Bridge\PermissionsCommand;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Scope\SystemIdentity;
use ArnaudMoncondhuy\Authorization\UserAuthorizer;
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

        foreach ([Authorizer::class, UserAuthorizer::class] as $alias) {
            if (!$container->hasAlias($alias)) {
                continue;
            }

            // Un alias public retient sa cible : la rendre publique ici la compterait utilisée.
            // Sur une définition que le paquet a marquée fautive — l'adaptateur « tiers » d'une
            // application sans fournisseur de comptes — le noyau de test s'arrêterait donc là
            // où une application se contente de ne pas avoir le service. Ce serait le confort
            // des tests qui déciderait du comportement examiné.
            $target = (string) $container->getAlias($alias);

            if ($container->hasDefinition($target) && $container->getDefinition($target)->hasErrors()) {
                continue;
            }

            $container->getAlias($alias)->setPublic(true);
        }
    }
}
