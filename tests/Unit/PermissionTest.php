<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\StockPermission;
use PHPUnit\Framework\TestCase;

/**
 * Ce qu'une application doit pouvoir faire de {@see Permission}.
 *
 * Deux contextes plutôt qu'un : c'est la façon dont les droits se déclarent, et c'est aussi
 * la seule configuration où la séparation des identités peut être mise en défaut.
 */
final class PermissionTest extends TestCase
{
    public function testAnEnumerationOfAnyContextSatisfiesTheContract(): void
    {
        self::assertSame('fixture.invoice.view', $this->identityOf(InvoicePermission::View));
        self::assertSame('fixture.stock.view', $this->identityOf(StockPermission::View));
    }

    public function testEveryIdentityIsPrefixedByItsContext(): void
    {
        foreach (InvoicePermission::cases() as $permission) {
            self::assertStringStartsWith('fixture.invoice.', $permission->id());
        }

        foreach (StockPermission::cases() as $permission) {
            self::assertStringStartsWith('fixture.stock.', $permission->id());
        }
    }

    /**
     * Deux contextes partagent le nom de cas `View` et ne doivent pas partager d'identité :
     * c'est l'identité qui désigne le droit, le nom du cas n'atteint jamais le contrôle
     * d'accès.
     */
    public function testNoIdentityIsSharedBetweenTwoContexts(): void
    {
        $invoice = array_map(static fn (InvoicePermission $p): string => $p->id(), InvoicePermission::cases());
        $stock = array_map(static fn (StockPermission $p): string => $p->id(), StockPermission::cases());

        self::assertSame([], array_intersect($invoice, $stock));
    }

    /**
     * Le contrat n'accepte que des énumérations : c'est le langage qui arrête une classe
     * ordinaire, à la ligne où elle se déclare. Ce test garde l'héritage qui porte cette
     * garantie — et avec elle l'inventaire que le conteneur compilé sait conserver.
     */
    public function testOnlyAnEnumerationCanCarryAPermission(): void
    {
        self::assertTrue(new \ReflectionClass(Permission::class)->implementsInterface(\UnitEnum::class));
    }

    /**
     * Reçoit le contrat et non l'énumération : ce qui passe ici passerait à n'importe quel
     * cas d'usage.
     */
    private function identityOf(Permission $permission): string
    {
        return $permission->id();
    }
}
