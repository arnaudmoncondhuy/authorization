<?php

declare(strict_types=1);

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Bridge\AuthorizationCollector;
use ArnaudMoncondhuy\Authorization\Bridge\TracingAuthorizer;
use ArnaudMoncondhuy\Authorization\Bridge\VoterSketch;
use ArnaudMoncondhuy\Authorization\Bridge\VoterSurvey;
use ArnaudMoncondhuy\Authorization\DependencyInjection\RefuseUserAuthorizerWithoutProviderPass;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Importé seulement là où WebProfilerBundle est enregistré, c'est-à-dire en dev et en test :
 * une application ne l'inscrit pas ailleurs. C'est ce qui fait qu'une installation en
 * production reçoit l'adaptateur nu et ne paie rien pour ceci.
 *
 * Le pare-feu est exigé par-dessus, et pour une raison mécanique : sans SecurityBundle, le
 * contrat `Authorizer` n'existe pas comme service, et il n'y aurait rien à décorer.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        // C'est l'alias du contrat qui est décoré, et non l'adaptateur qui le remplit : une
        // application qui aurait substitué le sien est observée pareillement, et c'est bien
        // l'alias que les cas d'usage reçoivent.
        ->set(TracingAuthorizer::class)
            ->decorate(Authorizer::class)
            ->args([service('.inner')])
            // Un processus qui sert plusieurs requêtes — un worker, un serveur qui reste en vie
            // — cumulerait sinon ce que chacune a demandé.
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set(AuthorizationCollector::class)
            ->args([
                service(TracingAuthorizer::class),
                service(VoterSurvey::class),
                service(VoterSketch::class),
                service(PermissionCatalog::class),
                // Les noms, et non les services : les injecter compterait l'adaptateur utilisé,
                // et RefuseUserAuthorizerWithoutProviderPass ferait alors échouer la compilation
                // de toute application sans fournisseur de comptes — c'est la raison qu'expose
                // déjà `config/doctor.php`.
                param(RefuseUserAuthorizerWithoutProviderPass::ON_BEHALF_PARAMETER),
                param(RefuseUserAuthorizerWithoutProviderPass::ON_BEHALF_PROVIDER_PARAMETER),
            ])
            ->tag('data_collector', [
                'id' => AuthorizationCollector::NAME,
                'template' => '@Authorization/data_collector/authorization.html.twig',
            ])
    ;
};
