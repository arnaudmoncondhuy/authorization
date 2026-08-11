<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Integration;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Bridge\MissingPermissionListener;
use ArnaudMoncondhuy\Authorization\Bridge\SecurityAuthorizer;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\InvoiceBook;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\UndeclaredUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Stock\AdjustStockUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Web\DeclaringController;
use ArnaudMoncondhuy\Authorization\Tests\Kernel\AuthorizationTestKernel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Le paquet éprouvé branché, dans un vrai noyau.
 *
 * C'est la seule suite qui puisse échouer si plus rien n'enregistre le tag ou les passes : les
 * tests unitaires bâtissent leur propre conteneur et resteraient verts sur un bundle qui
 * n'installerait plus rien.
 */
final class BundleWiringTest extends TestCase
{
    private ?AuthorizationTestKernel $kernel = null;

    /**
     * LE test du dispositif. Il devient rouge si `registerForAutoconfiguration()` disparaît de
     * la classe de bundle, si l'une des passes cesse d'être enregistrée, ou si les passes se
     * mettent à tourner avant que l'autoconfiguration ne soit résolue.
     *
     * Dans les trois cas, tous les autres contrôles resteraient verts et ne vérifieraient plus
     * rien.
     */
    public function testAUseCaseWithoutPermissionStopsTheCompilation(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/UndeclaredUseCase/');

        $this->boot(UndeclaredUseCase::class);
    }

    /**
     * L'autre sens : une surface qui redéclare un droit arrête aussi le démarrage.
     */
    public function testADeclarationOutsideAUseCaseStopsTheCompilation(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/DeclaringController/');

        $this->boot(DeclaringController::class);
    }

    /**
     * L'inventaire se garnit tout seul, depuis deux contextes, sans que rien ne l'ait déclaré
     * ailleurs que sur les cas d'usage eux-mêmes.
     */
    public function testTheCatalogIsBuiltFromTheRegisteredUseCases(): void
    {
        $container = $this->boot(ViewInvoiceUseCase::class, AdjustStockUseCase::class, InvoiceBook::class);

        $catalog = $container->get(PermissionCatalog::class);
        self::assertInstanceOf(PermissionCatalog::class, $catalog);

        self::assertSame(['fixture.invoice.view', 'fixture.stock.adjust'], $catalog->ids());
    }

    /**
     * Un cas d'usage reçoit le contrat, et c'est l'adaptateur Symfony qui lui arrive : c'est
     * ce que `config/security.php` câble, et il n'est importé que parce que SecurityBundle est
     * enregistré.
     */
    public function testTheContractResolvesToTheSecurityAdapter(): void
    {
        $container = $this->boot(ViewInvoiceUseCase::class, InvoiceBook::class);

        self::assertInstanceOf(SecurityAuthorizer::class, $container->get(Authorizer::class));
    }

    /**
     * L'écouteur qui traduit un refus en 403 est branché au répartiteur, et pas seulement
     * inscrit au conteneur : un service déclaré que rien n'appelle ne traduirait rien.
     */
    public function testTheRefusalListenerIsWiredToTheDispatcher(): void
    {
        $container = $this->boot(ViewInvoiceUseCase::class, InvoiceBook::class);

        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $listeners = array_map(
            // Le répartiteur rend un couple [objet, méthode] pour un écouteur invocable.
            static fn (callable $listener): string => get_debug_type(\is_array($listener) ? $listener[0] : $listener),
            $dispatcher->getListeners(KernelEvents::EXCEPTION),
        );

        self::assertContains(MissingPermissionListener::class, $listeners);
    }

    /**
     * Une application sans aucun cas d'usage démarre : le paquet n'impose rien tant qu'on ne
     * s'en sert pas.
     */
    public function testAnApplicationWithoutUseCasesBoots(): void
    {
        $container = $this->boot();

        $catalog = $container->get(PermissionCatalog::class);
        self::assertInstanceOf(PermissionCatalog::class, $catalog);

        self::assertSame([], $catalog->ids());
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
