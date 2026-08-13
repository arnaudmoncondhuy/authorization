<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Bridge\SecurityAuthorizer;
use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\CallerAccessChecker;
use PHPUnit\Framework\TestCase;

/**
 * L'adaptateur qui fait décider l'application, traversé pour de vrai.
 *
 * Ce qui compte ici n'est pas la décision — elle appartient aux voters — mais qu'elle soit
 * rendue telle quelle, et que la question posée soit l'identité du droit : décider sur autre
 * chose déciderait sur ce qui n'est ni stocké ni accordé.
 */
final class SecurityAuthorizerTest extends TestCase
{
    public function testItAnswersWhatTheAccessControlDecides(): void
    {
        $access = new SecurityAuthorizer(new CallerAccessChecker('fixture.invoice.view'));

        self::assertTrue($access->can(InvoicePermission::View));
        self::assertFalse($access->can(InvoicePermission::Finalize));
    }

    public function testItSubmitsTheIdentityOfThePermission(): void
    {
        $checker = new CallerAccessChecker();
        $access = new SecurityAuthorizer($checker);

        $access->can(InvoicePermission::Finalize);

        self::assertSame(['fixture.invoice.finalize'], $checker->asked);
    }

    public function testRequireLetsAGrantedPermissionThrough(): void
    {
        $checker = new CallerAccessChecker('fixture.invoice.finalize');
        $access = new SecurityAuthorizer($checker);

        $access->require(InvoicePermission::Finalize);

        self::assertSame(['fixture.invoice.finalize'], $checker->asked);
    }

    /**
     * Le refus nomme le droit manquant : c'est ce qui permet à la surface de dire lequel, et à
     * qui le reçoit de savoir quoi accorder.
     */
    public function testRequireStopsAnUngrantedPermissionAndNamesIt(): void
    {
        $access = new SecurityAuthorizer(new CallerAccessChecker());

        try {
            $access->require(InvoicePermission::Finalize);
            self::fail('Un droit non accordé doit arrêter le cas d\'usage.');
        } catch (MissingPermission $refusal) {
            self::assertSame(InvoicePermission::Finalize, $refusal->permission);
        }
    }
}
