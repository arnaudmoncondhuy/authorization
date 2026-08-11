<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Exige un droit qu'il ne déclare pas.
 *
 * Ce sens-là est le plus coûteux : le droit n'entre dans aucun inventaire, personne ne peut
 * l'accorder, et le verbe devient impossible pour tout le monde — sans recours depuis
 * l'application.
 */
#[RequiresPermission(InvoicePermission::View)]
final readonly class DemandsUndeclaredUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(): void
    {
        $this->access->require(InvoicePermission::View);
        $this->access->require(InvoicePermission::Create);
    }
}
