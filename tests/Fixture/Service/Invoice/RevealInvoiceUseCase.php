<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Proof;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Un cas d'usage à deux gravités : consulter, et révéler.
 *
 * La forme que l'exigence de preuve existe pour servir — deux droits sur un même verbe, dont
 * un seul fait ressortir le téléphone, et au moment précis où il sert.
 */
#[RequiresPermission(InvoicePermission::View, proof: Proof::Strong)]
#[RequiresPermission(InvoicePermission::Backdate, proof: Proof::Recent)]
final readonly class RevealInvoiceUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(bool $reveal = false): void
    {
        $this->access->require(InvoicePermission::View);

        if ($reveal) {
            $this->access->require(InvoicePermission::Backdate);
        }
    }
}
