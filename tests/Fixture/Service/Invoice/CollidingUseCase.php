<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\CollidingPermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Exige un droit dont l'identité est déjà portée par un autre contexte.
 */
#[RequiresPermission(CollidingPermission::View)]
final readonly class CollidingUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(): void
    {
        $this->access->require(CollidingPermission::View);
    }
}
