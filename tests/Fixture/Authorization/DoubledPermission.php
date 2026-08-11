<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization;

use ArnaudMoncondhuy\Authorization\Permission;

/**
 * Deux cas d'une même énumération qui rendent la même identité.
 *
 * La faute est vraisemblable : deux bras d'un `match` recopiés, ou deux cas regroupés dans un
 * seul bras. Une énumération adossée à des valeurs l'interdirait ; celle-ci calcule son
 * identité, et rien dans le langage ne s'y oppose.
 *
 * Ce qu'elle coûte est lourd : les deux droits se fondent en une seule entrée d'inventaire,
 * l'un écrase l'autre, et accorder ce nom ouvre les deux verbes.
 */
enum DoubledPermission implements Permission
{
    case First;
    case Second;

    public function id(): string
    {
        return 'fixture.doubled';
    }
}
