<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Choisit son droit par une valeur plutôt que d'écrire le cas en toutes lettres.
 *
 * Le droit est bien réclamé — le geste est gouverné — mais aucune lecture du corps ne peut le
 * rapprocher de ce que l'attribut déclare. Le signaler pour ce qu'il est vaut mieux que de
 * dire que la classe ne réclame jamais ce qu'elle déclare : ce serait faux, et cela enverrait
 * chercher au mauvais endroit.
 */
#[RequiresPermission(InvoicePermission::View)]
#[RequiresPermission(InvoicePermission::Create)]
final readonly class ComputesItsPermissionUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(bool $creating): void
    {
        $permission = $creating ? InvoicePermission::Create : InvoicePermission::View;

        $this->access->require($permission);
    }
}
