<?php

declare(strict_types=1);

use ArnaudMoncondhuy\Authorization\Bridge\PermissionsCommand;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Importé partout où symfony/console est installé, pare-feu ou non : l'inventaire ne réclame
 * que le catalogue, et une application sans pare-feu a autant besoin de savoir ce que son code
 * exige qu'une autre.
 *
 * Avec `VoterSurvey`, le seul service du paquet à réclamer PermissionCatalog : ils le
 * retiennent donc dans le conteneur d'une application qui ne l'injecte nulle part ailleurs.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(PermissionsCommand::class)
            ->args([service(PermissionCatalog::class)])
            ->tag('console.command')
    ;
};
