<?php

declare(strict_types=1);

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Bridge\DoctorCommand;
use ArnaudMoncondhuy\Authorization\DependencyInjection\RefuseUserAuthorizerWithoutProviderPass;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/*
 * Importé seulement là où symfony/console et un pare-feu sont installés : le docteur interroge
 * le contrôle d'accès, et sans pare-feu il n'aurait rien à examiner.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(DoctorCommand::class)
            ->args([
                service(PermissionCatalog::class),
                service(Authorizer::class),
                tagged_iterator('security.voter'),
                // Le nom de l'adaptateur, pas l'adaptateur : l'injecter suffirait à le compter
                // utilisé, et RefuseUserAuthorizerWithoutProviderPass ferait alors échouer la
                // compilation de toute application sans fournisseur de comptes — la commande
                // chargée de rapporter l'absence en deviendrait la cause.
                param(RefuseUserAuthorizerWithoutProviderPass::ON_BEHALF_PARAMETER),
            ])
            ->tag('console.command')
    ;
};
