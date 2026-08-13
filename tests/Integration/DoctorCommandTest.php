<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Integration;

use ArnaudMoncondhuy\Authorization\Bridge\DoctorCommand;
use ArnaudMoncondhuy\Authorization\Bridge\PermissionsCommand;
use ArnaudMoncondhuy\Authorization\Bridge\SecurityUserAuthorizer;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\FixturePermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\OverlappingPermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\UnguardedPermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\InvoiceBook;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Kernel\AuthorizationTestKernel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Ce que le docteur voit, et ce qu'il refuse de laisser passer.
 *
 * La faute qu'il cherche n'a aucun autre témoin : un droit qu'aucun voter ne prend en charge
 * est refusé à tout le monde, et rien — ni compilation, ni test, ni journal — ne le signale.
 */
final class DoctorCommandTest extends TestCase
{
    private ?AuthorizationTestKernel $kernel = null;

    /**
     * Le cas qui justifie la commande. Le droit est déclaré, l'inventaire le propose, un écran
     * d'attribution le montrerait — et personne ne se prononce dessus.
     */
    public function testItRefusesAPermissionThatNoVoterJudges(): void
    {
        $tester = $this->diagnose(ViewInvoiceUseCase::class, InvoiceBook::class);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('fixture.invoice.view', $tester->getDisplay());
    }

    public function testItIsSatisfiedWhenEveryPermissionHasAJudge(): void
    {
        $tester = $this->diagnose(ViewInvoiceUseCase::class, InvoiceBook::class, FixturePermissionVoter::class);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * Un voter qui refuse est un juge : ce qui compte est qu'il se prononce, pas ce qu'il
     * décide. Confondre les deux ferait passer une application entière pour malade.
     */
    public function testARefusingVoterCountsAsAJudge(): void
    {
        $tester = $this->diagnose(ViewInvoiceUseCase::class, InvoiceBook::class, FixturePermissionVoter::class);

        self::assertStringNotContainsString('fixture.invoice.view', $tester->getDisplay());
    }

    /**
     * L'autre faute qu'il est seul à voir, et elle élargit les droits au lieu de les fermer :
     * sous la stratégie « affirmative » de Symfony, il suffit qu'un des deux voters accorde.
     */
    public function testItReportsAPermissionJudgedByTwoVoters(): void
    {
        $tester = $this->diagnose(
            ViewInvoiceUseCase::class,
            InvoiceBook::class,
            FixturePermissionVoter::class,
            OverlappingPermissionVoter::class,
        );

        self::assertStringContainsString('plusieurs voters', $tester->getDisplay());
        self::assertStringContainsString(OverlappingPermissionVoter::class, $tester->getDisplay());
    }

    /**
     * Un recouvrement n'est pas toujours une faute — un voter qui ouvre tout à une poignée
     * d'administrateurs en est un usage légitime. Il se signale donc par défaut, et n'échoue
     * que sur demande.
     */
    public function testAnOverlapOnlyFailsWhenAsked(): void
    {
        $services = [
            ViewInvoiceUseCase::class,
            InvoiceBook::class,
            FixturePermissionVoter::class,
            OverlappingPermissionVoter::class,
        ];

        self::assertSame(Command::SUCCESS, $this->diagnose(...$services)->getStatusCode());
        self::assertSame(Command::FAILURE, $this->diagnoseStrictly(...$services)->getStatusCode());
    }

    /**
     * Le voter fragile ne fait plus tomber la commande.
     *
     * Laisser filer l'exception rendait un diagnostic muet sur tout le reste : le premier voter
     * qui lève emportait l'examen des droits suivants, et la sortie ne disait ni lesquels ni
     * combien. Il est nommé, l'examen continue, et le bilan reste rouge — un examen qui n'a pas
     * eu lieu ne se conclut pas au vert.
     */
    public function testAVoterThatRaisesIsReportedInsteadOfEndingTheExamination(): void
    {
        $tester = $this->diagnose(
            ViewInvoiceUseCase::class,
            InvoiceBook::class,
            UnguardedPermissionVoter::class,
        );

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('ont levé une exception', $tester->getDisplay());
        self::assertStringContainsString(UnguardedPermissionVoter::class, $tester->getDisplay());
    }

    /**
     * Et ce qu'il empêche de conclure est dit. Le droit apparaît orphelin parce que le seul
     * voter qui aurait pu le prendre en charge n'a pas pu répondre : l'écrire sans la réserve
     * enverrait écrire un voter qui existe déjà.
     */
    public function testAnOrphanFoundBesideARaisingVoterIsReportedWithAReservation(): void
    {
        $tester = $this->diagnose(
            ViewInvoiceUseCase::class,
            InvoiceBook::class,
            UnguardedPermissionVoter::class,
        );

        self::assertStringContainsString('fixture.invoice.view', $tester->getDisplay());
        self::assertStringContainsString('n\'a pas pu être examiné', $tester->getDisplay());
    }

    /**
     * Ce que la commande sait dire du contrat « répondre sur un tiers ». Branché, elle en donne
     * l'adaptateur ; absent, elle en donne la raison — et c'est le seul endroit où l'omission
     * se voit sans avoir à injecter le service pour s'en apercevoir.
     */
    public function testItNamesTheAdapterThatAnswersOnBehalfOfAThirdParty(): void
    {
        $tester = $this->diagnose(ViewInvoiceUseCase::class, InvoiceBook::class, FixturePermissionVoter::class);

        self::assertStringContainsString(SecurityUserAuthorizer::class, $tester->getDisplay());
    }

    /**
     * Et où il cherchera les comptes de ces tiers. Par défaut, la chaîne de tous les
     * fournisseurs que l'application déclare : c'est le service que SecurityExtension pose dès
     * qu'il en existe un, et le seul qui vaille aussi bien pour un annuaire que pour dix.
     */
    public function testItNamesTheDirectoryWhereAccountsAreSearched(): void
    {
        $tester = $this->diagnose(ViewInvoiceUseCase::class, InvoiceBook::class, FixturePermissionVoter::class);

        self::assertStringContainsString('Annuaire : security.user_providers', $tester->getDisplay());
    }

    /**
     * Une application qui restreint la recherche à un annuaire le lit ici, et c'est le seul
     * endroit où cette restriction se voit : elle ne change rien à la compilation, et tout aux
     * comptes qui seront trouvés.
     */
    public function testItNamesTheProviderTheApplicationChose(): void
    {
        $container = $this->start(new AuthorizationTestKernel(
            [ViewInvoiceUseCase::class, InvoiceBook::class, FixturePermissionVoter::class],
            userProvider: 'security.user.provider.concrete.in_memory',
        ));

        $command = $container->get(DoctorCommand::class);
        self::assertInstanceOf(DoctorCommand::class, $command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('Annuaire : security.user.provider.concrete.in_memory', $tester->getDisplay());
    }

    /**
     * Sur une application sans droit déclaré, il n'y a rien à examiner. Le dire plutôt que de
     * rendre un vert franc, qui laisserait croire qu'une installation a été vérifiée.
     */
    public function testItSaysSoWhenThereIsNothingToExamine(): void
    {
        $tester = $this->diagnose();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('rien à examiner', $tester->getDisplay());
    }

    public function testTheListingShowsWhereAnIdentityComesFrom(): void
    {
        $container = $this->boot(ViewInvoiceUseCase::class, InvoiceBook::class);
        $command = $container->get(PermissionsCommand::class);
        self::assertInstanceOf(PermissionsCommand::class, $command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('fixture.invoice.view', $tester->getDisplay());
        self::assertStringContainsString('InvoicePermission::View', $tester->getDisplay());
    }

    /** @param class-string ...$services */
    private function diagnose(string ...$services): CommandTester
    {
        return $this->examine([], ...$services);
    }

    /** @param class-string ...$services */
    private function diagnoseStrictly(string ...$services): CommandTester
    {
        return $this->examine(['--strict' => true], ...$services);
    }

    /**
     * @param array<string, bool> $options
     * @param class-string        ...$services
     */
    private function examine(array $options, string ...$services): CommandTester
    {
        $command = $this->boot(...$services)->get(DoctorCommand::class);
        self::assertInstanceOf(DoctorCommand::class, $command);

        $tester = new CommandTester($command);
        $tester->execute($options);

        return $tester;
    }

    /** @param class-string ...$services */
    private function boot(string ...$services): ContainerInterface
    {
        return $this->start(new AuthorizationTestKernel(array_values($services)));
    }

    private function start(AuthorizationTestKernel $kernel): ContainerInterface
    {
        $this->kernel = $kernel;
        $kernel->boot();

        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }
}
