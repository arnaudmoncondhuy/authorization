<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\UserAuthorizer;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Fait juger un tiers par les mêmes voters que l'appelant courant.
 *
 * C'est tout l'intérêt : la décision reste écrite au même endroit. Le contrôle d'accès de
 * Symfony sait répondre pour un utilisateur donné, et le modèle de l'application — rôles,
 * groupes, ensembles — n'a rien à savoir de qui pose la question.
 *
 * Le compte est chargé par le fournisseur de l'application, jamais fabriqué ici : un compte
 * inventé porterait les droits qu'on lui aurait donnés.
 */
final readonly class SecurityUserAuthorizer implements UserAuthorizer
{
    public function __construct(
        private UserAuthorizationCheckerInterface $accessChecker,
        /** @var UserProviderInterface<\Symfony\Component\Security\Core\User\UserInterface> */
        private UserProviderInterface $users,
    ) {
    }

    public function can(string $userIdentifier, Permission $permission): bool
    {
        try {
            $user = $this->users->loadUserByIdentifier($userIdentifier);
        } catch (AuthenticationException) {
            // Un identifiant qu'aucun compte ne porte n'a aucun droit. Refuser plutôt que
            // laisser remonter : la question est une lecture, et une lecture ne casse pas.
            return false;
        }

        return $this->accessChecker->isGrantedForUser($user, $permission->id());
    }
}
