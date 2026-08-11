<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\FixedAuthorizer;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\StockPermission;
use PHPUnit\Framework\TestCase;

/**
 * Ce que {@see \ArnaudMoncondhuy\Authorization\Authorizer} promet à un cas d'usage.
 */
final class AuthorizerTest extends TestCase
{
    public function testAGrantedPermissionIsRecognised(): void
    {
        $access = new FixedAuthorizer(InvoicePermission::View);

        self::assertTrue($access->can(InvoicePermission::View));
    }

    public function testAnUngrantedPermissionIsRefused(): void
    {
        $access = new FixedAuthorizer(InvoicePermission::View);

        self::assertFalse($access->can(InvoicePermission::Finalize));
    }

    /**
     * Un jeu de droits vide ne fait exception à rien : ce qui n'est pas accordé est refusé.
     */
    public function testNothingIsGrantedByDefault(): void
    {
        $access = new FixedAuthorizer();

        self::assertFalse($access->can(InvoicePermission::View));
    }

    /**
     * Les identités portent leur contexte : accorder une lecture ici n'en accorde pas une
     * ailleurs, malgré des cas de même nom.
     */
    public function testAGrantDoesNotCrossContexts(): void
    {
        $access = new FixedAuthorizer(InvoicePermission::View);

        self::assertFalse($access->can(StockPermission::View));
    }

    public function testRequireLetsAGrantedPermissionThrough(): void
    {
        $access = new FixedAuthorizer(InvoicePermission::Finalize);

        $access->require(InvoicePermission::Finalize);

        self::assertTrue($access->can(InvoicePermission::Finalize));
    }

    public function testRequireStopsAnUngrantedPermission(): void
    {
        $access = new FixedAuthorizer();

        $this->expectException(MissingPermission::class);

        $access->require(InvoicePermission::Finalize);
    }

    /**
     * Le droit manquant survit à la levée : c'est ce qui permet à une surface de dire
     * lequel, plutôt que de renvoyer un refus muet.
     */
    public function testTheRefusalNamesTheMissingPermission(): void
    {
        $access = new FixedAuthorizer();

        try {
            $access->require(InvoicePermission::Finalize);
            self::fail('Un droit non accordé doit arrêter le cas d\'usage.');
        } catch (MissingPermission $refusal) {
            self::assertSame(InvoicePermission::Finalize, $refusal->permission);
            self::assertStringContainsString('fixture.invoice.finalize', $refusal->getMessage());
        }
    }
}
