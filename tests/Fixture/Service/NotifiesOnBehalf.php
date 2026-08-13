<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service;

use ArnaudMoncondhuy\Authorization\UserAuthorizer;

/**
 * Un service qui a besoin de savoir ce qu'un autre que l'appelant a le droit de faire.
 *
 * Le cas le plus banal du contrat : ne pas notifier quelqu'un sur un module auquel il a perdu
 * l'accès. Il n'existe ici que pour injecter {@see UserAuthorizer} — c'est cette injection, et
 * elle seule, qui décide si une application sans fournisseur de comptes compile ou s'arrête.
 */
final readonly class NotifiesOnBehalf
{
    public function __construct(public UserAuthorizer $tiers)
    {
    }
}
