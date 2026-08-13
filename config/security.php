<?php

declare(strict_types=1);

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Bridge\SecurityAuthorizer;
use ArnaudMoncondhuy\Authorization\Bridge\SecurityUserAuthorizer;
use ArnaudMoncondhuy\Authorization\DependencyInjection\RefuseUserAuthorizerWithoutProviderPass;
use ArnaudMoncondhuy\Authorization\Scope\SystemIdentity;
use ArnaudMoncondhuy\Authorization\UserAuthorizer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;

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
    // Ce que `authorization:doctor` lit pour dire si le contrat « répondre sur un tiers » est
    // branché. Vrai par défaut puisqu'on vient de le déclarer ; remis à nul par la passe quand
    // l'application n'a aucun fournisseur de comptes.
    $container->parameters()
        ->set(RefuseUserAuthorizerWithoutProviderPass::ON_BEHALF_PARAMETER, SecurityUserAuthorizer::class)
    ;

    $container->services()
        ->set(SecurityAuthorizer::class)
            ->args([service(AuthorizationCheckerInterface::class)])

        // C'est l'alias, et non la classe, qu'un cas d'usage reçoit : il ne cite jamais
        // l'adaptateur, seulement le contrat.
        ->alias(Authorizer::class, SecurityAuthorizer::class)

        // Ce que détient un autre que l'appelant. Le fournisseur de comptes est celui de
        // l'application : ce contrat ne fabrique aucune identité.
        //
        // `security.user_providers` et non `UserProviderInterface` : SecurityExtension ne pose
        // cet alias-là que s'il existe EXACTEMENT un fournisseur, sans branche pour les autres
        // cas. Une application à deux fournisseurs — un modèle Doctrine et un annuaire LDAP,
        // par exemple — casserait sur une référence introuvable, et seulement le jour où
        // quelqu'un injecte le contrat. `security.user_providers` vaut pour un fournisseur
        // comme pour dix : alias vers l'unique, ChainUserProvider au-delà.
        //
        // Il reste un cas que ce nom ne couvre pas : à zéro fournisseur, il n'existe pas
        // davantage. C'est RefuseUserAuthorizerWithoutProviderPass qui le tient.
        ->set(SecurityUserAuthorizer::class)
            ->args([
                service(UserAuthorizationCheckerInterface::class),
                service(RefuseUserAuthorizerWithoutProviderPass::USER_PROVIDERS_SERVICE),
            ])
        ->alias(UserAuthorizer::class, SecurityUserAuthorizer::class)

        // Ce qu'une surface sans utilisateur injecte pour atteindre un verbe métier.
        ->set(SystemIdentity::class)
            ->args([service(TokenStorageInterface::class)])
    ;
};
