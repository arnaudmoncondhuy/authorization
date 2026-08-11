<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\PermissionCatalog;
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
}
