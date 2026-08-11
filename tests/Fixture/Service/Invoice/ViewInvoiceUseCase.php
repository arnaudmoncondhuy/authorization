<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Consulter une facture. La forme la plus simple d'un cas d'usage : un droit, un `require()`.
 */
#[RequiresPermission(InvoicePermission::View)]
final readonly class ViewInvoiceUseCase implements UseCase
{
    public function __construct(
        private Authorizer $access,
        private InvoiceBook $invoices,
    ) {
    }

    /** @return array{number: string, state: string} */
    public function __invoke(string $number): array
    {
        $this->access->require(InvoicePermission::View);

        return ['number' => $number, 'state' => $this->invoices->stateOf($number)];
    }
}
