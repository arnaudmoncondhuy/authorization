<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Proof;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\StockPermission;
use PHPUnit\Framework\TestCase;

/**
 * Ce que l'inventaire promet à l'écran qui accorde les droits.
 */
final class PermissionCatalogTest extends TestCase
{
    public function testItKeepsWhatItIsGiven(): void
    {
        $catalog = new PermissionCatalog([InvoicePermission::View, StockPermission::Adjust]);

        self::assertSame([InvoicePermission::View, StockPermission::Adjust], $catalog->all());
    }

    /**
     * Un même droit peut être exigé par plusieurs cas d'usage ; il ne se propose qu'une fois.
     */
    public function testTheSamePermissionIsListedOnce(): void
    {
        $catalog = new PermissionCatalog([InvoicePermission::View, InvoicePermission::View]);

        self::assertSame(['fixture.invoice.view'], $catalog->ids());
    }

    /**
     * L'ordre ne dépend pas de celui où les cas d'usage ont été rencontrés : une liste
     * affichée deux fois doit se ressembler.
     */
    public function testTheOrderIsTheOneOfIdentities(): void
    {
        $catalog = new PermissionCatalog([
            StockPermission::View,
            InvoicePermission::Finalize,
            InvoicePermission::Create,
        ]);

        self::assertSame(['fixture.invoice.create', 'fixture.invoice.finalize', 'fixture.stock.view'], $catalog->ids());
    }

    public function testAnEmptyCatalogAnswersEmptily(): void
    {
        $catalog = new PermissionCatalog();

        self::assertSame([], $catalog->all());
        self::assertSame([], $catalog->ids());
        self::assertFalse($catalog->isRequired('fixture.invoice.view'));
    }

    /**
     * La question que se pose une application devant un droit stocké : le code le réclame-t-il
     * encore ? Un « non » signale une case devenue sans effet.
     */
    public function testItAnswersOnAnIdentityComingFromElsewhere(): void
    {
        $catalog = new PermissionCatalog([InvoicePermission::View]);

        self::assertTrue($catalog->isRequired('fixture.invoice.view'));
        self::assertFalse($catalog->isRequired('invoice.renamed'));
    }

    /**
     * Un droit qui n'exige rien de plus et un droit qui n'existe pas se traitent pareil : c'est
     * la détention du droit, vérifiée avant, qui sépare les deux cas.
     */
    public function testAPermissionWithoutAStatedProofDemandsNone(): void
    {
        $catalog = new PermissionCatalog([InvoicePermission::View]);

        self::assertSame(Proof::None, $catalog->proofFor('fixture.invoice.view'));
        self::assertSame(Proof::None, $catalog->proofFor('inconnu'));
    }

    /**
     * La liste que le docteur et le panneau affichent : elle ne retient que ce qui exige, sans
     * quoi ils énuméreraient tout le catalogue pour ne rien dire.
     */
    public function testItListsOnlyThePermissionsThatDemandAProof(): void
    {
        $catalog = new PermissionCatalog(
            [InvoicePermission::View, InvoicePermission::Finalize],
            ['fixture.invoice.finalize' => Proof::Recent, 'fixture.invoice.view' => Proof::None],
        );

        self::assertSame(Proof::Recent, $catalog->proofFor('fixture.invoice.finalize'));
        self::assertSame(['fixture.invoice.finalize' => Proof::Recent], $catalog->proofs());
    }
}
