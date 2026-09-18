<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Confirmation;
use ArnaudMoncondhuy\Authorization\MissingConfirmation;
use ArnaudMoncondhuy\Authorization\Permission;

/**
 * Accorde tous les droits, et ne recueille aucune confirmation.
 *
 * Le cas qui sépare un refus d'un acte en attente : le droit est là, l'identité aussi, et rien
 * n'a dit qu'on voulait le poser.
 */
final readonly class UnconfirmedAuthorizer implements Authorizer
{
    public function __construct(private Confirmation $required = Confirmation::Typed)
    {
    }

    public function can(Permission $permission): bool
    {
        return true;
    }

    public function require(Permission $permission): void
    {
        throw MissingConfirmation::of($permission, $this->required);
    }
}
