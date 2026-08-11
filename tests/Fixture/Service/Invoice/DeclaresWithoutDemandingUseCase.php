<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Déclare deux droits et n'en réclame qu'un — la faute qu'aucun contrôle de compilation ne
 * peut voir, puisqu'elle est dans le corps.
 *
 * Elle s'installe sans mauvaise intention : on ajoute une déclaration parce que l'écran des
 * droits doit proposer la case, et on oublie la ligne qui l'applique. La case existe alors,
 * on la coche ou on la décoche, et cela ne change rien.
 */
#[RequiresPermission(InvoicePermission::View)]
#[RequiresPermission(InvoicePermission::Create)]
final readonly class DeclaresWithoutDemandingUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    /** @return array<string, string> */
    public function __invoke(): array
    {
        $this->access->require(InvoicePermission::View);

        return ['state' => 'created'];
    }
}
