<?php

declare(strict_types=1);

use ArnaudMoncondhuy\Authorization\Bridge\MissingPermissionListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * L'écouteur n'a d'objet que si des requêtes HTTP passent ; le manifeste exige de toute façon
 * symfony/http-kernel — la classe de bundle en vient — et symfony/event-dispatcher, seul
 * lecteur du tag posé ici.
 *
 * Le tag est posé en toutes lettres plutôt que par #[AsEventListener] : l'attribut n'est lu
 * que sur les services qu'une application autoconfigure, et celui-ci est déclaré ici.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(MissingPermissionListener::class)
            ->tag('kernel.event_listener', ['event' => 'kernel.exception'])
    ;
};
