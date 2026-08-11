<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

/**
 * Un livre de factures en mémoire, qui retient ce qu'on lui a demandé.
 *
 * C'est ce que les tests examinent pour vérifier qu'un refus arrive AVANT toute lecture et
 * toute écriture : un livre resté vide prouve que le cas d'usage s'est arrêté à temps.
 */
final class InvoiceBook
{
    /** @var list<string> */
    public array $read = [];

    /** @var list<array{number: string, backdatedTo: ?\DateTimeImmutable}> */
    public array $finalized = [];

    public function stateOf(string $number): string
    {
        $this->read[] = $number;

        return 'draft';
    }

    public function finalize(string $number, ?\DateTimeImmutable $backdatedTo = null): void
    {
        $this->finalized[] = ['number' => $number, 'backdatedTo' => $backdatedTo];
    }
}
