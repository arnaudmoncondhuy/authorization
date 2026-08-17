<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Aucune route : le noyau de test ne sert aucune page.
 *
 * Ce fichier n'existe que parce que WebProfilerBundle réclame un routeur — il en tire le lien
 * vers le profileur — et qu'un routeur réclame une ressource. Le déclarer vide dit exactement
 * ce qui est éprouvé : le câblage du panneau, et pas son affichage dans un navigateur.
 */
return static function (RoutingConfigurator $routes): void {
};
