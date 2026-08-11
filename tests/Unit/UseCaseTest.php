<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\FixedAuthorizer;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\FinalizeInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\InvoiceBook;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use PHPUnit\Framework\TestCase;

/**
 * La forme d'un cas d'usage, éprouvée sur ses deux chemins.
 *
 * Le refus se teste en premier, et sur le livre autant que sur l'exception : lever au bon
 * moment ne suffit pas, encore faut-il n'avoir rien lu ni rien écrit avant.
 */
final class UseCaseTest extends TestCase
{
    public function testARefusedReadTouchesNothing(): void
    {
        $invoices = new InvoiceBook();
        $useCase = new ViewInvoiceUseCase(new FixedAuthorizer(), $invoices);

        try {
            $useCase('F-2026-001');
            self::fail('Une lecture sans droit doit être arrêtée.');
        } catch (MissingPermission $refusal) {
            self::assertSame(InvoicePermission::View, $refusal->permission);
        }

        self::assertSame([], $invoices->read);
    }

    public function testAGrantedReadGoesThrough(): void
    {
        $invoices = new InvoiceBook();
        $useCase = new ViewInvoiceUseCase(new FixedAuthorizer(InvoicePermission::View), $invoices);

        $result = $useCase('F-2026-001');

        self::assertSame(['number' => 'F-2026-001', 'state' => 'draft'], $result);
        self::assertSame(['F-2026-001'], $invoices->read);
    }

    public function testTheBasePermissionIsEnoughForTheOrdinaryGesture(): void
    {
        $invoices = new InvoiceBook();
        $useCase = new FinalizeInvoiceUseCase(new FixedAuthorizer(InvoicePermission::Finalize), $invoices);

        $useCase('F-2026-002');

        self::assertSame([['number' => 'F-2026-002', 'backdatedTo' => null]], $invoices->finalized);
    }

    /**
     * Le second droit ne se réclame qu'au moment du geste sensible : sans lui, la finalisation
     * ordinaire passe, l'antidatage est arrêté, et rien n'est écrit.
     */
    public function testTheFinePermissionIsDemandedOnlyBySensitiveGesture(): void
    {
        $invoices = new InvoiceBook();
        $useCase = new FinalizeInvoiceUseCase(new FixedAuthorizer(InvoicePermission::Finalize), $invoices);

        try {
            $useCase('F-2026-003', new \DateTimeImmutable('2026-01-01'));
            self::fail('Un antidatage sans droit doit être arrêté.');
        } catch (MissingPermission $refusal) {
            self::assertSame(InvoicePermission::Backdate, $refusal->permission);
        }

        self::assertSame([], $invoices->finalized);
    }

    public function testBothPermissionsAllowTheSensitiveGesture(): void
    {
        $invoices = new InvoiceBook();
        $useCase = new FinalizeInvoiceUseCase(
            new FixedAuthorizer(InvoicePermission::Finalize, InvoicePermission::Backdate),
            $invoices,
        );

        $backdatedTo = new \DateTimeImmutable('2026-01-01');
        $useCase('F-2026-004', $backdatedTo);

        self::assertSame([['number' => 'F-2026-004', 'backdatedTo' => $backdatedTo]], $invoices->finalized);
    }

    /**
     * Ce que l'attribut annonce est lisible par réflexion : c'est de là que viennent
     * l'inventaire des droits et le contrôle de leur déclaration.
     */
    public function testDeclaredPermissionsAreReadableFromTheClass(): void
    {
        $attributes = new \ReflectionClass(FinalizeInvoiceUseCase::class)->getAttributes(RequiresPermission::class);

        $declared = array_map(
            static fn (\ReflectionAttribute $a): string => $a->newInstance()->permission->id(),
            $attributes,
        );

        self::assertSame(['fixture.invoice.finalize', 'fixture.invoice.backdate'], $declared);
    }
}
