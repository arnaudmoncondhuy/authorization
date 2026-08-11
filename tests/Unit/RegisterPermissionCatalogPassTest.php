<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\DependencyInjection\RegisterPermissionCatalogPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\Tag;
use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\StockPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\CollidingUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\FinalizeInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Stock\AdjustStockUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Comment l'inventaire se garnit, et ce qu'il refuse de laisser passer.
 */
final class RegisterPermissionCatalogPassTest extends TestCase
{
    /**
     * Les droits sont rassemblés depuis plusieurs contextes, et un cas d'usage qui en exige
     * deux les apporte tous les deux.
     */
    public function testItGathersEveryDeclaredPermission(): void
    {
        $container = $this->containerWith(
            ViewInvoiceUseCase::class,
            FinalizeInvoiceUseCase::class,
            AdjustStockUseCase::class,
        );

        new RegisterPermissionCatalogPass()->process($container);

        self::assertSame(
            ['fixture.invoice.backdate', 'fixture.invoice.finalize', 'fixture.invoice.view', 'fixture.stock.adjust'],
            $this->catalogOf($container)->ids(),
        );
    }

    /**
     * Un droit qu'aucun cas d'usage n'exige n'entre pas dans l'inventaire, même si son
     * énumération le déclare : la liste dit ce que le code réclame.
     */
    public function testItIgnoresAPermissionNoUseCaseDemands(): void
    {
        $container = $this->containerWith(ViewInvoiceUseCase::class);

        new RegisterPermissionCatalogPass()->process($container);

        self::assertFalse($this->catalogOf($container)->isRequired(InvoicePermission::Create->id()));
        self::assertFalse($this->catalogOf($container)->isRequired(StockPermission::View->id()));
    }

    /**
     * Le seul rempart contre une collision entre contextes, et il arrête la compilation :
     * deux domaines partageraient sinon un droit sans que rien ne le dise.
     */
    public function testItRefusesTwoPermissionsSharingAnIdentity(): void
    {
        $container = $this->containerWith(ViewInvoiceUseCase::class, CollidingUseCase::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/invoice\.view/');

        new RegisterPermissionCatalogPass()->process($container);
    }

    public function testTheSamePermissionDemandedTwiceIsNotACollision(): void
    {
        $container = $this->containerWith(ViewInvoiceUseCase::class);
        $container->register('another_reader', ViewInvoiceUseCase::class)->addTag(Tag::USE_CASE);

        new RegisterPermissionCatalogPass()->process($container);

        self::assertSame(['fixture.invoice.view'], $this->catalogOf($container)->ids());
    }

    public function testAnApplicationWithoutUseCasesGetsAnEmptyCatalog(): void
    {
        $container = new ContainerBuilder();

        new RegisterPermissionCatalogPass()->process($container);

        self::assertSame([], $this->catalogOf($container)->ids());
    }

    /** @param class-string ...$classes */
    private function containerWith(string ...$classes): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ($classes as $class) {
            $container->register($class, $class)->addTag(Tag::USE_CASE);
        }

        return $container;
    }

    private function catalogOf(ContainerBuilder $container): PermissionCatalog
    {
        /** @var list<Permission> $permissions */
        $permissions = $container->getDefinition(PermissionCatalog::class)->getArgument(0);

        return new PermissionCatalog($permissions);
    }
}
