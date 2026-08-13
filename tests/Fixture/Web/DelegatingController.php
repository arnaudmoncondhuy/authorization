<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Web;

use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Une porte d'entrée qui lit une demande, appelle un verbe métier, et rend une réponse.
 *
 * Elle ne décide rien : le droit est réclamé par le verbe, une seule fois, quelle que soit la
 * porte qui l'atteint.
 *
 * L'attribut est celui qu'une application pose : c'est lui qui fait tagger la classe par
 * l'autoconfiguration, et donc examiner par la passe des surfaces dans un vrai noyau.
 */
#[AsController]
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
