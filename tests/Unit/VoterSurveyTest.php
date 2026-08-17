<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Bridge\VoterCoverage;
use ArnaudMoncondhuy\Authorization\Bridge\VoterSurvey;
use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\StockPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\CountingRaisingVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\FixturePermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\OverlappingPermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\PartialPermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\UnguardedPermissionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Ce que l'examen constate, sans rien en conclure.
 *
 * La faute qu'il cherche n'a aucun autre témoin : un droit qu'aucun voter ne prend en charge
 * est refusé à tout le monde, et rien — ni compilation, ni test, ni journal — ne le signale.
 *
 * Deux surfaces le lisent, la ligne de commande et la barre de debug. C'est ici que se joue le
 * fait qu'elles lisent la même chose.
 */
final class VoterSurveyTest extends TestCase
{
    /**
     * Le cas qui justifie l'examen. Le droit est déclaré, l'inventaire le propose, un écran
     * d'attribution le montrerait — et personne ne se prononce dessus.
     */
    public function testItNamesAPermissionThatNoVoterJudges(): void
    {
        $coverage = $this->examine([InvoicePermission::View]);

        self::assertSame([InvoicePermission::View], $coverage->unjudged());
    }

    public function testAJudgedPermissionIsNotAnOrphan(): void
    {
        $coverage = $this->examine([InvoicePermission::View], new FixturePermissionVoter());

        self::assertSame([], $coverage->unjudged());
    }

    /**
     * Un voter qui refuse est un juge : ce qui compte est qu'il se prononce, pas ce qu'il
     * décide. Confondre les deux ferait passer une application entière pour malade —
     * `FixturePermissionVoter` n'accorde rien, et prend pourtant bien en charge.
     */
    public function testARefusingVoterCountsAsAJudge(): void
    {
        $coverage = $this->examine([InvoicePermission::View], new FixturePermissionVoter());

        self::assertSame([FixturePermissionVoter::class], $coverage->judgesOf('fixture.invoice.view'));
    }

    /**
     * L'autre faute qu'il est seul à voir, et elle élargit les droits au lieu de les fermer :
     * sous la stratégie « affirmative » de Symfony, il suffit qu'un des deux voters accorde.
     */
    public function testItReportsAPermissionJudgedByTwoVoters(): void
    {
        $coverage = $this->examine(
            [InvoicePermission::View],
            new FixturePermissionVoter(),
            new OverlappingPermissionVoter(),
        );

        self::assertSame(
            ['fixture.invoice.view' => [FixturePermissionVoter::class, OverlappingPermissionVoter::class]],
            $coverage->shared(),
        );
    }

    /** Un seul juge n'est pas un recouvrement, et n'a donc rien à signaler. */
    public function testASinglyJudgedPermissionIsNotShared(): void
    {
        $coverage = $this->examine([InvoicePermission::View], new FixturePermissionVoter());

        self::assertSame([], $coverage->shared());
    }

    /**
     * Le voter fragile ne fait pas tomber l'examen.
     *
     * Laisser filer l'exception rendrait le constat muet sur tout le reste : le premier voter
     * qui lève emporterait l'examen des droits suivants, et rien ne dirait ni lesquels ni
     * combien. Il est nommé, et l'examen continue sur les autres voters comme sur les autres
     * droits.
     */
    public function testAVoterThatRaisesIsReportedInsteadOfEndingTheExamination(): void
    {
        $coverage = $this->examine(
            [InvoicePermission::View, StockPermission::Adjust],
            new UnguardedPermissionVoter(),
            new FixturePermissionVoter(),
        );

        self::assertArrayHasKey(UnguardedPermissionVoter::class, $coverage->raised());
        self::assertSame([FixturePermissionVoter::class], $coverage->judgesOf('fixture.stock.adjust'));
    }

    /**
     * Et il n'est compté ni juge ni absent : ce qu'il aurait répondu reste inconnu. Le porter
     * dans les juges certifierait un droit que personne ne prend peut-être en charge. Il reste
     * compté parmi les voters enregistrés : il est bien installé, c'est répondre qu'il ne sait
     * pas.
     */
    public function testAVoterThatRaisesIsNeitherAJudgeNorAbsent(): void
    {
        $coverage = $this->examine([InvoicePermission::View], new UnguardedPermissionVoter());

        self::assertSame([], $coverage->judgesOf('fixture.invoice.view'));
        self::assertSame([InvoicePermission::View], $coverage->unjudged());
        self::assertSame(1, $coverage->voters);
    }

    /**
     * Un voter qui a levé n'est pas réinterrogé : le refaire n'apprendrait rien, allongerait le
     * rapport, et le panneau de la barre de debug rejoue cet examen à chaque page.
     */
    public function testAVoterThatRaisesIsNotAskedAgain(): void
    {
        $voter = new CountingRaisingVoter();

        $this->examine([InvoicePermission::View, InvoicePermission::Create, StockPermission::Adjust], $voter);

        self::assertSame(1, $voter->asked);
    }

    /**
     * L'état courant d'une application qui grandit : l'énumération gagne un cas, le `supports()`
     * ne le suit pas, et les droits voisins gardent leur juge.
     */
    public function testItSeparatesTheJudgedFromTheUnjudgedWithinAContext(): void
    {
        $coverage = $this->examine(
            [InvoicePermission::View, InvoicePermission::Finalize],
            new PartialPermissionVoter(),
        );

        self::assertSame([InvoicePermission::Finalize], $coverage->unjudged());
    }

    /** Sans droit déclaré, il n'y a rien à examiner — et rien à conclure non plus. */
    public function testAnEmptyCatalogIsExaminedEmptily(): void
    {
        $coverage = $this->examine([], new FixturePermissionVoter());

        self::assertSame([], $coverage->examined());
        self::assertSame([], $coverage->unjudged());
        self::assertSame([], $coverage->shared());
        self::assertSame([], $coverage->raised());
        self::assertSame(1, $coverage->voters);
    }

    /** L'ordre est celui du catalogue, pour qu'un constat lu deux fois se ressemble. */
    public function testItExaminesTheWholeCatalogInItsOrder(): void
    {
        $coverage = $this->examine([StockPermission::Adjust, InvoicePermission::View]);

        self::assertSame([InvoicePermission::View, StockPermission::Adjust], $coverage->examined());
    }

    /** @param list<Permission> $permissions */
    private function examine(array $permissions, VoterInterface ...$voters): VoterCoverage
    {
        return (new VoterSurvey(new PermissionCatalog($permissions), $voters))->examine();
    }
}
