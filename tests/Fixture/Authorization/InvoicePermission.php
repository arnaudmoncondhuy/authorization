<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization;

use ArnaudMoncondhuy\Authorization\Permission;

/**
 * Les droits du contexte « facturation », tels qu'une application les déclarerait.
 *
 * Vit avec les tests : le paquet ne livre aucun droit, ceux-ci n'existent que pour l'exercer.
 */
enum InvoicePermission: string implements Permission
{
    case View = 'fixture.invoice.view';
    case Create = 'fixture.invoice.create';
    case Finalize = 'fixture.invoice.finalize';
    case Backdate = 'fixture.invoice.backdate';

    public function id(): string
    {
        return $this->value;
    }
}
