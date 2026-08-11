<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Finaliser une facture, et l'antidater si on le demande.
 *
 * Deux droits pour un seul verbe : finaliser se réclame toujours, antidater ne se réclame
 * qu'au moment où une date est fournie. Un compte qui n'a que le premier finalise, et se voit
 * arrêter s'il tente d'antidater.
 */
#[RequiresPermission(InvoicePermission::Finalize)]
#[RequiresPermission(InvoicePermission::Backdate)]
final readonly class FinalizeInvoiceUseCase implements UseCase
{
    public function __construct(
        private Authorizer $access,
        private InvoiceBook $invoices,
    ) {
    }

    /** @return array{number: string, state: string} */
    public function __invoke(string $number, ?\DateTimeImmutable $backdatedTo = null): array
    {
        $this->access->require(InvoicePermission::Finalize);

        if (null !== $backdatedTo) {
            $this->access->require(InvoicePermission::Backdate);
        }

        $this->invoices->finalize($number, $backdatedTo);

        return ['number' => $number, 'state' => 'finalized'];
    }
}
