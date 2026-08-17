<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Bridge\SecurityAuthorizer;
use ArnaudMoncondhuy\Authorization\InsufficientProof;
use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Proof;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\CallerAccessChecker;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\ProvenIdentity;
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
        $access = $this->authorizer(new CallerAccessChecker('fixture.invoice.view'));

        self::assertTrue($access->can(InvoicePermission::View));
        self::assertFalse($access->can(InvoicePermission::Finalize));
    }

    public function testItSubmitsTheIdentityOfThePermission(): void
    {
        $checker = new CallerAccessChecker();
        $access = $this->authorizer($checker);

        $access->can(InvoicePermission::Finalize);

        self::assertSame(['fixture.invoice.finalize'], $checker->asked);
    }

    public function testRequireLetsAGrantedPermissionThrough(): void
    {
        $checker = new CallerAccessChecker('fixture.invoice.finalize');
        $access = $this->authorizer($checker);

        $access->require(InvoicePermission::Finalize);

        self::assertSame(['fixture.invoice.finalize'], $checker->asked);
    }

    /**
     * Le refus nomme le droit manquant : c'est ce qui permet à la surface de dire lequel, et à
     * qui le reçoit de savoir quoi accorder.
     */
    public function testRequireStopsAnUngrantedPermissionAndNamesIt(): void
    {
        $access = $this->authorizer(new CallerAccessChecker());

        try {
            $access->require(InvoicePermission::Finalize);
            self::fail('Un droit non accordé doit arrêter le cas d\'usage.');
        } catch (MissingPermission $refusal) {
            self::assertSame(InvoicePermission::Finalize, $refusal->permission);
        }
    }

    /**
     * Le droit ne suffit plus dès que la déclaration exige une preuve. Le refus nomme les deux
     * — le droit en jeu et le niveau demandé — parce que c'est ce dont la surface a besoin pour
     * décider où renvoyer.
     */
    public function testRequireStopsAGrantedPermissionWhenIdentityIsNotProvenEnough(): void
    {
        $access = $this->authorizer(
            new CallerAccessChecker('fixture.invoice.finalize'),
            proofs: ['fixture.invoice.finalize' => Proof::Recent],
            proven: Proof::Strong,
        );

        try {
            $access->require(InvoicePermission::Finalize);
            self::fail('Une preuve insuffisante doit arrêter le cas d\'usage.');
        } catch (InsufficientProof $detour) {
            self::assertSame(InvoicePermission::Finalize, $detour->permission);
            self::assertSame(Proof::Recent, $detour->required);
        }
    }

    public function testRequireLetsThroughWhenIdentityIsProvenEnough(): void
    {
        $access = $this->authorizer(
            new CallerAccessChecker('fixture.invoice.finalize'),
            proofs: ['fixture.invoice.finalize' => Proof::Strong],
            proven: Proof::Recent,
        );

        $access->require(InvoicePermission::Finalize);

        self::expectNotToPerformAssertions();
    }

    /**
     * Le droit d'abord : on ne fait pas prouver son identité à quelqu'un pour lui refuser
     * l'action ensuite, et le détour ne révèle pas l'existence d'un acte à qui ne pouvait pas
     * le poser.
     */
    public function testAMissingPermissionIsReportedBeforeAMissingProof(): void
    {
        $access = $this->authorizer(
            new CallerAccessChecker(),
            proofs: ['fixture.invoice.finalize' => Proof::Recent],
        );

        $this->expectException(MissingPermission::class);

        $access->require(InvoicePermission::Finalize);
    }

    /**
     * Le nul refuse au lieu de laisser passer. Il ne se présente pas dans une application
     * compilée — la passe s'y oppose — mais c'est le sens dans lequel une garantie doit tomber
     * si jamais elle tombe.
     */
    public function testWithoutAJudgeAnyProofRequirementRefuses(): void
    {
        $access = new SecurityAuthorizer(
            new CallerAccessChecker('fixture.invoice.finalize'),
            new PermissionCatalog([], ['fixture.invoice.finalize' => Proof::Strong]),
        );

        $this->expectException(InsufficientProof::class);

        $access->require(InvoicePermission::Finalize);
    }

    /**
     * Consulter ne fait jamais ressortir le téléphone : masquer l'action à qui la détient
     * parce qu'il n'a pas prouvé récemment lui ferait chercher un droit qu'il possède.
     */
    public function testCanIgnoresTheProofRequirement(): void
    {
        $access = $this->authorizer(
            new CallerAccessChecker('fixture.invoice.finalize'),
            proofs: ['fixture.invoice.finalize' => Proof::Recent],
        );

        self::assertTrue($access->can(InvoicePermission::Finalize));
    }

    /** @param array<string, Proof> $proofs */
    private function authorizer(
        CallerAccessChecker $checker,
        array $proofs = [],
        Proof $proven = Proof::None,
    ): SecurityAuthorizer {
        return new SecurityAuthorizer(
            $checker,
            new PermissionCatalog([], $proofs),
            new ProvenIdentity($proven),
        );
    }
}
