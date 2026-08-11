<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization;

use ArnaudMoncondhuy\Authorization\Permission;

/**
 * Un contexte qui reprend, par mégarde, une identité déjà employée ailleurs.
 *
 * La faute est vraisemblable : deux énumérations vivent dans deux dossiers, personne ne les
 * lit côte à côte, et rien dans le langage n'empêche la répétition. Ce qu'elle coûterait est
 * lourd — accorder ce droit pour un contexte l'accorderait pour l'autre.
 */
enum CollidingPermission: string implements Permission
{
    case View = 'fixture.invoice.view';

    public function id(): string
    {
        return $this->value;
    }
}
