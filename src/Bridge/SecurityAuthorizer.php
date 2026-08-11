<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\Permission;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Fait décider les droits par le contrôle d'accès de l'application.
 *
 * L'identité du droit devient l'attribut qu'on lui soumet : tout le modèle de l'application —
 * ce qu'un rôle accorde, ce qu'un ensemble contient, ce qu'un compte s'est vu attribuer — vit
 * dans les voters, et rien de tout cela ne remonte jusqu'aux cas d'usage.
 *
 * Ce paquet ne fournit aucun voter : ce qui décide appartient à l'application.
 *
 * Aucun cycle à craindre : un voter décide, il n'a jamais besoin de ce service pour le faire.
 */
final readonly class SecurityAuthorizer implements Authorizer
{
    public function __construct(private AuthorizationCheckerInterface $accessChecker)
    {
    }

    public function can(Permission $permission): bool
    {
        return $this->accessChecker->isGranted($permission->id());
    }

    public function require(Permission $permission): void
    {
        if (!$this->can($permission)) {
            throw MissingPermission::of($permission);
        }
    }
}
