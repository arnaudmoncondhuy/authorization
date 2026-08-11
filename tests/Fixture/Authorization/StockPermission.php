<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization;

use ArnaudMoncondhuy\Authorization\Permission;

/**
 * Les droits du contexte « stock ».
 *
 * Second contexte, pour que la séparation des identités ait quelque chose de réel à
 * surveiller : son cas `View` porte le même nom que celui de la facturation, et une identité
 * différente.
 */
enum StockPermission: string implements Permission
{
    case View = 'fixture.stock.view';
    case Adjust = 'fixture.stock.adjust';

    public function id(): string
    {
        return $this->value;
    }
}
