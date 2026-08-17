<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Integration;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Bridge\AuthorizationCollector;
use ArnaudMoncondhuy\Authorization\Bridge\DoctorCommand;
use ArnaudMoncondhuy\Authorization\Bridge\SecurityAuthorizer;
use ArnaudMoncondhuy\Authorization\Bridge\TracingAuthorizer;
use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\FixturePermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\FinalizeInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\InvoiceBook;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Kernel\AuthorizationTestKernel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Le panneau branché dans une vraie application de développement.
 *
 * Les objets éprouvés à la main resteraient verts même si plus rien ne les enregistrait : c'est
 * ici, et ici seulement, qu'on voit le décorateur prendre la place du contrat et le gabarit
 * trouver son espace de noms.
 */
final class ProfilerWiringTest extends TestCase
{
    private const string PANEL = '@Authorization/data_collector/authorization.html.twig';

    private ?AuthorizationTestKernel $kernel = null;

    public function testTheContractIsObservedWhereTheProfilerIs(): void
    {
        $container = $this->boot(profiler: true);

        self::assertInstanceOf(TracingAuthorizer::class, $container->get(Authorizer::class));
    }

    /**
     * Et nu partout ailleurs. C'est ce qui fait qu'une application en production ne paie rien
     * pour ceci : le décorateur n'y est pas, pas même inerte.
     */
    public function testTheContractIsBareWithoutTheProfiler(): void
    {
        $container = $this->boot(profiler: false);

        self::assertInstanceOf(SecurityAuthorizer::class, $container->get(Authorizer::class));
        self::assertFalse($container->has(AuthorizationCollector::class));
    }

    /** Ce que le décorateur observe est bien ce que les verbes reçoivent. */
    public function testWhatAVerbAsksIsNoted(): void
    {
        $container = $this->boot(profiler: true);

        $this->exercise($container);

        $traced = $container->get(TracingAuthorizer::class);
        self::assertInstanceOf(TracingAuthorizer::class, $traced);

        self::assertSame(
            [['id' => 'fixture.invoice.view', 'kind' => 'require', 'granted' => false, 'caller' => ViewInvoiceUseCase::class]],
            $traced->calls(),
        );
    }

    /**
     * Le docteur nomme l'adaptateur qui décide, et non le décorateur qui l'observe. La commande
     * tourne en dev, où le décorateur est justement là : sans cette précaution, elle
     * appellerait « contrat » celui qui ne décide rien.
     */
    public function testTheDoctorStillNamesTheAdapterThatDecides(): void
    {
        $container = $this->boot(profiler: true);

        $command = $container->get(DoctorCommand::class);
        self::assertInstanceOf(DoctorCommand::class, $command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('Contrat  : '.SecurityAuthorizer::class, $tester->getDisplay());
        self::assertStringNotContainsString(TracingAuthorizer::class, $tester->getDisplay());
    }

    /**
     * Le gabarit vit dans le paquet et se cite par l'espace de noms du bundle. Rien d'autre ne
     * le vérifie : une faute de chemin ne se verrait qu'en ouvrant la barre.
     */
    public function testThePanelRendersWhatThePageDid(): void
    {
        $container = $this->boot(profiler: true);
        $this->exercise($container);

        $panel = $this->render($container, 'panel');

        // Le nom court se lit à part de son espace de noms, comme les panneaux de Symfony le
        // font : c'est le nom court qu'on cherche des yeux.
        self::assertStringContainsString('ViewInvoiceUseCase', $panel);
        self::assertStringContainsString('Tests\Fixture\Service\Invoice', $panel);
        self::assertStringContainsString('fixture.invoice.view', $panel);
        self::assertStringContainsString('refusé', $panel);
        self::assertStringContainsString(SecurityAuthorizer::class, $panel);
    }

    /**
     * Et le constat sur les voters, avec le squelette de celui qui manque — le même que rend
     * `authorization:doctor`, puisque c'est le même examen.
     */
    public function testThePanelJoinsTheVoterThatIsMissing(): void
    {
        $container = $this->boot(profiler: true, judged: false);
        $this->exercise($container);

        $panel = $this->render($container, 'panel');

        self::assertStringContainsString('ne sont jugés par personne', $panel);
        self::assertStringContainsString('final class InvoiceVoter extends Voter', $panel);
    }

    /**
     * Les badges du tableau se colorent avec les classes que la feuille de style du profileur
     * connaît. `label-status-*` n'en fait pas partie hors du menu : un verdict qui la porte sort
     * en gris, et c'est justement le verdict qu'on vient lire.
     */
    public function testTheVerdictCarriesAColourTheProfilerKnows(): void
    {
        $container = $this->boot(profiler: true);
        $this->exercise($container);

        $panel = $this->render($container, 'panel');

        self::assertStringContainsString('class="label status-error"', $panel);
        self::assertStringNotContainsString('label-status-', $panel);
    }

    /**
     * Une explication n'a de sens qu'à côté de la mention qu'elle éclaire : affichée seule, elle
     * fait chercher au lecteur une ligne que le tableau ne porte pas.
     */
    public function testThePanelExplainsNothingThatIsNotOnScreen(): void
    {
        $container = $this->boot(profiler: true);
        $this->exercise($container);

        $panel = $this->render($container, 'panel');

        self::assertStringNotContainsString('<code>can()</code>', $panel);
        self::assertStringNotContainsString('ne veut pas dire', $panel);
    }

    /** Et elle paraît dès que la mention est là. */
    public function testThePanelExplainsAnUntouchedDeclaration(): void
    {
        $container = $this->boot(profiler: true, verb: FinalizeInvoiceUseCase::class);

        $verb = $container->get(FinalizeInvoiceUseCase::class);
        self::assertInstanceOf(FinalizeInvoiceUseCase::class, $verb);

        try {
            $verb('F-1');
            self::fail('Le verbe aurait dû être arrêté.');
        } catch (MissingPermission) {
        }

        $panel = $this->render($container, 'panel');

        self::assertStringContainsString('pas touché sur cette page', $panel);
        self::assertStringContainsString('ne veut pas dire', $panel);
    }

    /** Une page qui ne traverse aucun verbe l'écrit, plutôt que d'afficher un tableau vide. */
    public function testThePanelSaysSoWhenNoVerbWasCrossed(): void
    {
        $container = $this->boot(profiler: true);

        $panel = $this->render($container, 'panel');

        self::assertStringContainsString('aucun verbe métier', $panel);
    }

    /** L'entrée du menu du profileur, qui porte la même couleur que l'icône. */
    public function testTheMenuEntryCarriesTheStatus(): void
    {
        $container = $this->boot(profiler: true, judged: false);

        $menu = $this->render($container, 'menu');

        self::assertStringContainsString('Autorisation', $menu);
        self::assertStringContainsString('label-status-error', $menu);
    }

    /**
     * Le verbe s'arrête sur un refus : le pare-feu du noyau de test n'authentifie personne, et
     * le voter de fixture n'accorde rien. C'est le chemin le plus riche à observer.
     */
    private function exercise(ContainerInterface $container): void
    {
        $verb = $container->get(ViewInvoiceUseCase::class);
        self::assertInstanceOf(ViewInvoiceUseCase::class, $verb);

        try {
            $verb('F-1');
            self::fail('Le verbe aurait dû être arrêté.');
        } catch (MissingPermission) {
        }
    }

    private function render(ContainerInterface $container, string $block): string
    {
        $collector = $container->get(AuthorizationCollector::class);
        self::assertInstanceOf(AuthorizationCollector::class, $collector);

        $collector->collect(new Request(), new Response());

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->load(self::PANEL)->renderBlock($block, ['collector' => $collector]);
    }

    /** @param ?class-string $verb un second verbe à exposer, quand le premier ne suffit pas */
    private function boot(bool $profiler, bool $judged = true, ?string $verb = null): ContainerInterface
    {
        $services = [ViewInvoiceUseCase::class, InvoiceBook::class];

        if (null !== $verb) {
            $services[] = $verb;
        }

        if ($judged) {
            $services[] = FixturePermissionVoter::class;
        }

        $this->kernel = new AuthorizationTestKernel($services, profiler: $profiler);
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
