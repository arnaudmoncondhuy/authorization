<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\DependencyInjection\CheckOnlyUseCasesDeclarePermissionsPass;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\FinalizeInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\InvoiceBook;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Web\DeclaringController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Où un droit a le droit d'être déclaré, et où il ne l'a pas.
 */
final class CheckOnlyUseCasesDeclarePermissionsPassTest extends TestCase
{
    public function testItRefusesADeclarationMadeOutsideAUseCase(): void
    {
        $container = $this->containerWith(DeclaringController::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/DeclaringController/');

        new CheckOnlyUseCasesDeclarePermissionsPass()->process($container);
    }

    public function testItAcceptsDeclarationsMadeByUseCases(): void
    {
        $container = $this->containerWith(ViewInvoiceUseCase::class, FinalizeInvoiceUseCase::class);

        new CheckOnlyUseCasesDeclarePermissionsPass()->process($container);

        self::assertTrue($container->hasDefinition(ViewInvoiceUseCase::class));
    }

    /**
     * La règle ne vise que ce qui déclare : une classe qui ne porte pas l'attribut ne la
     * concerne pas, quelle que soit sa nature.
     */
    public function testItLeavesAloneWhatDeclaresNothing(): void
    {
        $container = $this->containerWith(InvoiceBook::class, \stdClass::class);

        new CheckOnlyUseCasesDeclarePermissionsPass()->process($container);

        self::assertTrue($container->hasDefinition(InvoiceBook::class));
    }

    /**
     * Aucun tag n'est requis : le contrôle parcourt toutes les définitions du conteneur.
     * Une surface qui déclarerait un droit ne porte pas le tag des cas d'usage, et serait
     * pourtant invisible si la règle s'appuyait dessus.
     */
    public function testItSeesADeclarationOnAnUntaggedService(): void
    {
        $container = new ContainerBuilder();
        $container->register('page', DeclaringController::class);

        $this->expectException(LogicException::class);

        new CheckOnlyUseCasesDeclarePermissionsPass()->process($container);
    }

    /**
     * Une classe inscrite deux fois au conteneur ne compte que pour une faute : c'est la
     * classe qui est en cause, pas chacun de ses services.
     */
    public function testAClassRegisteredTwiceIsNamedOnce(): void
    {
        $container = $this->containerWith(DeclaringController::class);
        $container->register('another_definition', DeclaringController::class);

        try {
            new CheckOnlyUseCasesDeclarePermissionsPass()->process($container);
            self::fail('Une déclaration hors cas d\'usage doit être refusée.');
        } catch (LogicException $refusal) {
            self::assertSame(1, substr_count($refusal->getMessage(), DeclaringController::class));
        }
    }

    /** @param class-string ...$classes */
    private function containerWith(string ...$classes): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ($classes as $class) {
            $container->register($class, $class);
        }

        return $container;
    }
}
