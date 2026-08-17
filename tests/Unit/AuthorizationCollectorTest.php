<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Bridge\AuthorizationCollector;
use ArnaudMoncondhuy\Authorization\Bridge\TracingAuthorizer;
use ArnaudMoncondhuy\Authorization\Bridge\VoterSketch;
use ArnaudMoncondhuy\Authorization\Bridge\VoterSurvey;
use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\FixedAuthorizer;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\FixturePermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\OverlappingPermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\UnguardedPermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\FinalizeInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\InvoiceBook;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Ce que le panneau montre d'une page, et la couleur qu'il donne à son icône.
 *
 * La règle de couleur est éprouvée ici plutôt que dans le gabarit : c'est une règle, et une
 * règle qu'on ne peut pas éprouver se met à mentir sans qu'on le sache.
 */
final class AuthorizationCollectorTest extends TestCase
{
    /**
     * Le cœur du panneau : le lien entre un verbe et les droits qu'il exige. Le contrôle
     * d'accès de Symfony voit passer une identité, il ne sait pas quel verbe l'a demandée.
     */
    public function testItGathersWhatEachVerbDeclaredAndAsked(): void
    {
        $tracing = $this->tracing(InvoicePermission::Finalize, InvoicePermission::Backdate);
        (new FinalizeInvoiceUseCase($tracing, new InvoiceBook()))('F-1');

        $verbs = $this->collect($tracing, [InvoicePermission::Finalize, InvoicePermission::Backdate])->verbs();

        self::assertCount(1, $verbs);
        self::assertSame(FinalizeInvoiceUseCase::class, $verbs[0]['name']);
        self::assertSame(['fixture.invoice.finalize', 'fixture.invoice.backdate'], $verbs[0]['declared']);
        self::assertSame(['fixture.invoice.finalize'], array_column($verbs[0]['calls'], 'id'));
    }

    /**
     * Un droit déclaré que la page n'a pas touché est dit tel quel, et pas autrement : le verbe
     * ne réclame « antidater » qu'au moment où une date arrive. Ce n'est pas la même question
     * que « ce droit est-il jamais réclamé », qui demande de lire les corps de méthode.
     */
    public function testADeclaredPermissionUntouchedOnThisPageIsSaidSo(): void
    {
        $tracing = $this->tracing(InvoicePermission::Finalize, InvoicePermission::Backdate);
        (new FinalizeInvoiceUseCase($tracing, new InvoiceBook()))('F-1');

        $verbs = $this->collect($tracing, [InvoicePermission::Finalize, InvoicePermission::Backdate])->verbs();

        self::assertSame(['fixture.invoice.backdate'], $verbs[0]['absent']);
    }

    /** Et il ne l'est plus dès que la page l'a réclamé. */
    public function testNothingIsAbsentWhenEveryDeclaredPermissionWasAsked(): void
    {
        $tracing = $this->tracing(InvoicePermission::Finalize, InvoicePermission::Backdate);
        (new FinalizeInvoiceUseCase($tracing, new InvoiceBook()))('F-1', new \DateTimeImmutable('2026-01-01'));

        $verbs = $this->collect($tracing, [InvoicePermission::Finalize, InvoicePermission::Backdate])->verbs();

        self::assertSame([], $verbs[0]['absent']);
    }

    /** Deux verbes traversés font deux entrées, dans l'ordre où ils ont demandé. */
    public function testEachVerbHasItsOwnEntry(): void
    {
        $tracing = $this->tracing(InvoicePermission::Finalize, InvoicePermission::Backdate, InvoicePermission::View);
        (new FinalizeInvoiceUseCase($tracing, new InvoiceBook()))('F-1');
        (new ViewInvoiceUseCase($tracing, new InvoiceBook()))('F-1');

        $verbs = $this->collect($tracing, [InvoicePermission::Finalize, InvoicePermission::Backdate, InvoicePermission::View])->verbs();

        self::assertSame(
            [FinalizeInvoiceUseCase::class, ViewInvoiceUseCase::class],
            array_column($verbs, 'name'),
        );
    }

    /**
     * Ce qui n'est passé par aucun verbe se rassemble sous une seule entrée sans nom : ce qui
     * compte est qu'aucun verbe n'ait été traversé, pas d'où chaque appel venait.
     */
    public function testWhatCameFromOutsideAVerbIsGatheredApart(): void
    {
        $tracing = $this->tracing(InvoicePermission::View);
        $tracing->can(InvoicePermission::View);

        $verbs = $this->collect($tracing, [InvoicePermission::View])->verbs();

        self::assertCount(1, $verbs);
        self::assertNull($verbs[0]['name']);
        self::assertSame([], $verbs[0]['declared']);
    }

    /**
     * Les compteurs de l'icône ne portent que sur les demandes. Une consultation refusée est le
     * cas courant — un bouton masqué — et la compter ferait rougir une page normale.
     */
    public function testOnlyDemandsAreCounted(): void
    {
        $tracing = $this->tracing(InvoicePermission::View);
        $tracing->can(InvoicePermission::Finalize);
        (new ViewInvoiceUseCase($tracing, new InvoiceBook()))('F-1');

        $collector = $this->collect($tracing, [InvoicePermission::View, InvoicePermission::Finalize]);

        self::assertSame(1, $collector->granted());
        self::assertSame(0, $collector->refused());
    }

    public function testItCountsARefusedDemand(): void
    {
        $tracing = $this->tracing();
        $this->refuse($tracing);

        $collector = $this->collect($tracing, [InvoicePermission::View]);

        self::assertSame(0, $collector->granted());
        self::assertSame(1, $collector->refused());
    }

    /** Le catalogue entier d'un côté, ce que la page en a touché de l'autre. */
    public function testItSaysHowMuchOfTheCatalogThisPageTouched(): void
    {
        $tracing = $this->tracing(InvoicePermission::View);
        (new ViewInvoiceUseCase($tracing, new InvoiceBook()))('F-1');
        $tracing->can(InvoicePermission::View);

        $collector = $this->collect($tracing, [InvoicePermission::View, InvoicePermission::Finalize]);

        self::assertSame(2, $collector->catalog());
        // Deux appels, un seul droit : ce qui est compté est ce qui a été touché, pas combien
        // de fois.
        self::assertSame(1, $collector->touched());
    }

    /** L'adaptateur qui décide, et non le décorateur qui l'observe. */
    public function testItNamesTheContractThatDecides(): void
    {
        $collector = $this->collect($this->tracing(), []);

        self::assertSame(FixedAuthorizer::class, $collector->contract());
    }

    public function testARefusalTurnsTheIconRed(): void
    {
        $tracing = $this->tracing();
        $this->refuse($tracing);

        self::assertSame('red', $this->collect($tracing, [InvoicePermission::View], new FixturePermissionVoter())->status());
    }

    /**
     * Un droit que personne ne juge ferme un verbe à tout le monde sans qu'aucune erreur ne le
     * dise. C'est aussi grave qu'un refus, et l'icône le dit pareillement.
     */
    public function testAPermissionWithoutAJudgeTurnsTheIconRed(): void
    {
        $tracing = $this->tracing(InvoicePermission::View);
        (new ViewInvoiceUseCase($tracing, new InvoiceBook()))('F-1');

        self::assertSame('red', $this->collect($tracing, [InvoicePermission::View])->status());
    }

    /** Un voter qui lève rendra une erreur serveur là où on attendait un refus. */
    public function testAVoterThatRaisesTurnsTheIconRed(): void
    {
        $tracing = $this->tracing(InvoicePermission::View);
        (new ViewInvoiceUseCase($tracing, new InvoiceBook()))('F-1');

        $collector = $this->collect($tracing, [InvoicePermission::View], new UnguardedPermissionVoter());

        self::assertSame('red', $collector->status());
        self::assertArrayHasKey(UnguardedPermissionVoter::class, $collector->raised());
    }

    /**
     * Un droit déclaré que la page n'a pas touché mérite un coup d'œil, pas une alarme : le cas
     * est courant et souvent normal.
     */
    public function testAnUntouchedDeclarationTurnsTheIconYellow(): void
    {
        $tracing = $this->tracing(InvoicePermission::Finalize, InvoicePermission::Backdate);
        (new FinalizeInvoiceUseCase($tracing, new InvoiceBook()))('F-1');

        $collector = $this->collect(
            $tracing,
            [InvoicePermission::Finalize, InvoicePermission::Backdate],
            new FixturePermissionVoter(),
        );

        self::assertSame('yellow', $collector->status());
    }

    /** Un recouvrement de voters aussi : il élargit les droits, il n'en ferme aucun. */
    public function testAnOverlapTurnsTheIconYellow(): void
    {
        $tracing = $this->tracing(InvoicePermission::View);
        (new ViewInvoiceUseCase($tracing, new InvoiceBook()))('F-1');

        $collector = $this->collect(
            $tracing,
            [InvoicePermission::View],
            new FixturePermissionVoter(),
            new OverlappingPermissionVoter(),
        );

        self::assertSame('yellow', $collector->status());
    }

    public function testAPageWithNothingToSayLeavesTheIconAlone(): void
    {
        $tracing = $this->tracing(InvoicePermission::View);
        (new ViewInvoiceUseCase($tracing, new InvoiceBook()))('F-1');

        $collector = $this->collect($tracing, [InvoicePermission::View], new FixturePermissionVoter());

        self::assertSame('', $collector->status());
    }

    /**
     * Une page qui ne traverse aucun verbe ne dit rien plutôt que de dire zéro : c'est le
     * gabarit qui l'écrit en toutes lettres, à partir d'une liste vide.
     */
    public function testAPageThatCrossesNoVerbCollectsNoVerb(): void
    {
        $collector = $this->collect($this->tracing(), [InvoicePermission::View], new FixturePermissionVoter());

        self::assertSame([], $collector->verbs());
        self::assertSame(0, $collector->granted());
        self::assertSame(0, $collector->refused());
        self::assertSame('', $collector->status());
    }

    /** Au droit sans juge, le panneau joint le voter qui manque, comme le docteur le fait. */
    public function testItJoinsTheSketchOfTheMissingVoter(): void
    {
        $collector = $this->collect($this->tracing(), [InvoicePermission::View]);

        self::assertSame(['fixture.invoice.view'], $collector->unjudged());
        self::assertArrayHasKey('src/Security/InvoiceVoter.php', $collector->sketches());
    }

    /** Une installation qui se tient n'a rien à proposer. */
    public function testItJoinsNoSketchWhenEveryPermissionHasAJudge(): void
    {
        $collector = $this->collect($this->tracing(), [InvoicePermission::View], new FixturePermissionVoter());

        self::assertSame([], $collector->sketches());
    }

    private function tracing(Permission ...$granted): TracingAuthorizer
    {
        return new TracingAuthorizer(new FixedAuthorizer(...$granted));
    }

    /** Le verbe s'arrête sur un refus, et c'est ce refus qu'on veut voir noté. */
    private function refuse(TracingAuthorizer $tracing): void
    {
        try {
            (new ViewInvoiceUseCase($tracing, new InvoiceBook()))('F-1');
            self::fail('Le verbe aurait dû être arrêté.');
        } catch (MissingPermission) {
        }
    }

    /** @param list<Permission> $permissions */
    private function collect(TracingAuthorizer $tracing, array $permissions, VoterInterface ...$voters): AuthorizationCollector
    {
        $catalog = new PermissionCatalog($permissions);

        $collector = new AuthorizationCollector(
            $tracing,
            new VoterSurvey($catalog, $voters),
            new VoterSketch(),
            $catalog,
        );

        $collector->collect(new Request(), new Response());

        return $collector;
    }
}
