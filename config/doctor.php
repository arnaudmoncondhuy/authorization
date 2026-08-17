<?php

declare(strict_types=1);

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Bridge\DoctorCommand;
use ArnaudMoncondhuy\Authorization\Bridge\VoterSketch;
use ArnaudMoncondhuy\Authorization\Bridge\VoterSurvey;
use ArnaudMoncondhuy\Authorization\DependencyInjection\RefuseUserAuthorizerWithoutProviderPass;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Importé seulement là où symfony/console et un pare-feu sont installés : le docteur interroge
 * le contrôle d'accès, et sans pare-feu il n'aurait rien à examiner.
 *
 * L'examen lui-même est déclaré par `config/voters.php`, qu'il partage avec le panneau de la
 * barre de debug.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(DoctorCommand::class)
            ->args([
                service(VoterSurvey::class),
                service(VoterSketch::class),
                service(Authorizer::class),
                // Le nom de l'adaptateur, pas l'adaptateur : l'injecter suffirait à le compter
                // utilisé, et RefuseUserAuthorizerWithoutProviderPass ferait alors échouer la
                // compilation de toute application sans fournisseur de comptes — la commande
                // chargée de rapporter l'absence en deviendrait la cause.
                param(RefuseUserAuthorizerWithoutProviderPass::ON_BEHALF_PARAMETER),
                // Et le nom de l'annuaire où il cherche, pour la même raison.
                param(RefuseUserAuthorizerWithoutProviderPass::ON_BEHALF_PROVIDER_PARAMETER),
            ])
            ->tag('console.command')
    ;
};
