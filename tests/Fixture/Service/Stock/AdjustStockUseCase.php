<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Stock;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\StockPermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Un cas d'usage d'un autre contexte, pour que le recensement ait à rassembler des droits qui
 * ne viennent pas tous du même endroit.
 */
#[RequiresPermission(StockPermission::Adjust)]
final readonly class AdjustStockUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(): void
    {
        $this->access->require(StockPermission::Adjust);
    }
}
