<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Un cas d'usage qui ne déclare aucun droit — la faute que le contrôle de compilation existe
 * pour refuser.
 *
 * Elle n'est jamais inscrite au conteneur d'une application : le test qui s'en sert construit
 * le sien.
 */
final readonly class UndeclaredUseCase implements UseCase
{
    /** @return array<string, string> */
    public function __invoke(): array
    {
        return ['state' => 'unchecked'];
    }
}
