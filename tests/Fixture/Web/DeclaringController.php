<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Web;

use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;

/**
 * Une surface qui redéclare un droit au lieu de s'en remettre au cas d'usage — la faute que
 * le contrôle de compilation existe pour refuser.
 *
 * Elle est plausible et c'est ce qui la rend dangereuse : durcir la page sans toucher au
 * cas d'usage, ou l'inverse, ne se voit nulle part.
 */
#[RequiresPermission(InvoicePermission::View)]
final readonly class DeclaringController
{
    public function __invoke(): string
    {
        return 'invoice';
    }
}
