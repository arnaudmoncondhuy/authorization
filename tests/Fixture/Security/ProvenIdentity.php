<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use ArnaudMoncondhuy\Authorization\Proof;
use ArnaudMoncondhuy\Authorization\ProofOfIdentity;

/**
 * Un juge qui a constaté un niveau, et répond à partir de lui.
 *
 * Tient lieu du paquet qui sait authentifier : ce qu'il constate est posé à la construction,
 * et la comparaison est celle de l'échelle — c'est elle qu'on éprouve ici, pas la façon dont
 * un vrai juge la remplit.
 */
final readonly class ProvenIdentity implements ProofOfIdentity
{
    public function __construct(private Proof $proven = Proof::None)
    {
    }

    public function meets(Proof $required): bool
    {
        return $this->proven->satisfies($required);
    }
}
