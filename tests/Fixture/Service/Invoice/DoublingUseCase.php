<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\DoubledPermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Exige deux droits que leur énumération a fondus sous une seule identité.
 */
#[RequiresPermission(DoubledPermission::First)]
#[RequiresPermission(DoubledPermission::Second)]
final readonly class DoublingUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(): void
    {
        $this->access->require(DoubledPermission::First);
        $this->access->require(DoubledPermission::Second);
    }
}
