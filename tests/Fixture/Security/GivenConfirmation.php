<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use ArnaudMoncondhuy\Authorization\Confirmation;
use ArnaudMoncondhuy\Authorization\ConfirmationOfIntent;

/**
 * Un témoin qui a recueilli un niveau de confirmation, et répond à partir de lui.
 *
 * Tient lieu du paquet qui sait lire ce qu'un formulaire a porté : ce qu'il a recueilli est posé
 * à la construction, et la comparaison est celle de l'échelle.
 */
final readonly class GivenConfirmation implements ConfirmationOfIntent
{
    public function __construct(private Confirmation $given = Confirmation::None)
    {
    }

    public function meets(Confirmation $required): bool
    {
        return $this->given->satisfies($required);
    }
}
