<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\InsufficientProof;
use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\Proof;

/**
 * Accorde tous les droits, et n'admet aucune preuve d'identité.
 *
 * Le cas qui sépare un refus d'un détour : le droit est là, ce qui manque est ailleurs.
 */
final readonly class UnprovenAuthorizer implements Authorizer
{
    public function __construct(private Proof $required = Proof::Recent)
    {
    }

    public function can(Permission $permission): bool
    {
        return true;
    }

    public function require(Permission $permission): void
    {
        throw InsufficientProof::of($permission, $this->required);
    }
}
