<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Integration;

use ArnaudMoncondhuy\Authorization\Bridge\DoctorCommand;
use ArnaudMoncondhuy\Authorization\Bridge\PermissionsCommand;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\FixturePermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\OverlappingPermissionVoter;
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
        $this->kernel = new AuthorizationTestKernel(array_values($services));
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }
}
