<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Teste le droit sans jamais s'y tenir — un mot de différence avec un cas d'usage correct,
 * et le droit n'est jamais appliqué.
 *
 * La faute s'écrit sans mauvaise intention : on commence par vouloir journaliser un refus,
 * puis on oublie d'agir dessus.
 */
#[RequiresPermission(InvoicePermission::View)]
final readonly class TestsWithoutRequiringUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    /** @return array<string, bool> */
    public function __invoke(): array
    {
        return ['autorise' => $this->access->can(InvoicePermission::View)];
    }
}
