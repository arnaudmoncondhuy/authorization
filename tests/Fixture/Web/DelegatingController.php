<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Web;

use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;

/**
 * Une porte d'entrée qui lit une demande, appelle un verbe métier, et rend une réponse.
 *
 * Elle ne décide rien : le droit est réclamé par le verbe, une seule fois, quelle que soit la
 * porte qui l'atteint.
 */
final readonly class DelegatingController
{
    public function __construct(private ViewInvoiceUseCase $viewInvoice)
    {
    }

    /** @return array{number: string, state: string} */
    public function __invoke(string $number): array
    {
        return ($this->viewInvoice)($number);
    }
}
