<?php

declare(strict_types=1);

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Bridge\SecurityAuthorizer;
use ArnaudMoncondhuy\Authorization\Bridge\SecurityUserAuthorizer;
use ArnaudMoncondhuy\Authorization\Scope\SystemIdentity;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ArnaudMoncondhuy\Authorization\UserAuthorizer;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Importé seulement quand SecurityBundle est enregistré : sans lui,
 * AuthorizationCheckerInterface n'est pas un service, et ce fichier rendrait le paquet
 * ininstallable dans toute application sans pare-feu.
 *
 * Le service est déclaré ici plutôt que par attribut : un bundle ne parcourt pas ses fichiers
 * pour les inscrire, donc rien ne lirait un #[AsAlias] posé sur la classe.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(SecurityAuthorizer::class)
            ->args([service(AuthorizationCheckerInterface::class)])

        // C'est l'alias, et non la classe, qu'un cas d'usage reçoit : il ne cite jamais
        // l'adaptateur, seulement le contrat.
        ->alias(Authorizer::class, SecurityAuthorizer::class)

        // Ce que détient un autre que l'appelant. Le fournisseur de comptes est celui de
        // l'application : ce contrat ne fabrique aucune identité.
        ->set(SecurityUserAuthorizer::class)
            ->args([service(UserAuthorizationCheckerInterface::class), service(UserProviderInterface::class)])
        ->alias(UserAuthorizer::class, SecurityUserAuthorizer::class)

        // Ce qu'une surface sans utilisateur injecte pour atteindre un verbe métier.
        ->set(SystemIdentity::class)
            ->args([service(TokenStorageInterface::class)])
    ;
};
