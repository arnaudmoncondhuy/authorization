<?php

declare(strict_types=1);

use ArnaudMoncondhuy\Authorization\Bridge\VoterSketch;
use ArnaudMoncondhuy\Authorization\Bridge\VoterSurvey;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/*
 * Ce qui interroge les voters, et ce qui propose celui qui manque. Deux surfaces les lisent —
 * `authorization:doctor` et le panneau de la barre de debug — et c'est pour cela qu'ils vivent
 * ici plutôt que chez l'une d'elles : logés chez la commande, le panneau devrait la citer pour
 * poser la même question, ou la reposer autrement.
 *
 * Importé seulement quand SecurityBundle est enregistré : sans pare-feu, il n'y a pas de voter
 * à interroger.
 *
 * Aucun des deux n'est utile seul : le conteneur les retire de lui-même quand ni la commande
 * ni le panneau ne sont là pour les lire.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(VoterSurvey::class)
            ->args([
                service(PermissionCatalog::class),
                tagged_iterator('security.voter'),
            ])

        ->set(VoterSketch::class)
    ;
};
