<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Unscanned;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * La faute que le rapport décrit : un cas d'usage dans un fichier que sa place ne nomme pas.
 *
 * La classe a été renommée, le fichier ne l'a pas suivi. `class_exists()` sur le nom déduit du
 * chemin rend faux, et la classe passait à travers toute la lecture — attribut compris. Elle
 * n'est pourtant pas inoffensive : elle porte un verbe métier et réclame un droit, et rien ne
 * confrontait plus l'un à l'autre.
 *
 * Elle est ici pour être manquée. C'est le fichier, et non elle, que le contrôle doit nommer.
 */
#[RequiresPermission(InvoicePermission::View)]
final readonly class InvoiceUseCaseUnderAnotherName implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(): void
    {
        $this->access->require(InvoicePermission::Create);
    }
}
