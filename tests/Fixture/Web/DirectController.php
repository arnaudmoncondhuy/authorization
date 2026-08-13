<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Web;

use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\InvoiceBook;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Une porte d'entrée qui agit sans passer par un verbe métier.
 *
 * Elle ne déclare aucun droit, n'en réclame aucun, et les trois autres contrôles la laissent
 * donc tranquille — c'est par là qu'un inconnu peut agir sans compte. La faute s'écrit sans
 * mauvaise intention : injecter le dépôt est plus court que passer par le cas d'usage.
 *
 * L'attribut est celui qu'une application pose : c'est lui qui fait tagger la classe par
 * l'autoconfiguration, et donc examiner par la passe des surfaces dans un vrai noyau.
 */
#[AsController]
final readonly class DirectController
{
    public function __construct(private InvoiceBook $invoices)
    {
    }

    public function __invoke(string $number): void
    {
        $this->invoices->finalize($number);
    }
}
