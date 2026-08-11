<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\Permission;

/**
 * Un jeu de droits fixé à la construction, sans compte connecté ni base.
 *
 * C'est ce qui permet d'éprouver un cas d'usage sur ses deux chemins — accordé et refusé —
 * en deux lignes, là où le vrai contrôle d'accès réclamerait une requête et une session.
 */
final class FixedAuthorizer implements Authorizer
{
    /** @var list<string> */
    private readonly array $granted;

    public function __construct(Permission ...$granted)
    {
        $this->granted = array_values(array_map(static fn (Permission $p): string => $p->id(), $granted));
    }

    public function can(Permission $permission): bool
    {
        return \in_array($permission->id(), $this->granted, true);
    }

    public function require(Permission $permission): void
    {
        if (!$this->can($permission)) {
            throw MissingPermission::of($permission);
        }
    }
}
