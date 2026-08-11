<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\DependencyInjection\CheckUseCasesDeclarePermissionsPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\Tag;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\FinalizeInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\UndeclaredUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Ce que le contrôle refuse, et ce qu'il laisse passer.
 *
 * Le conteneur est bâti ici plutôt qu'emprunté à une application : y inscrire un cas d'usage
 * fautif suffirait sinon à l'empêcher de démarrer, y compris pour les autres tests.
 */
final class CheckUseCasesDeclarePermissionsPassTest extends TestCase
{
    public function testItRefusesAUseCaseThatDeclaresNoPermission(): void
    {
        $container = $this->containerWith(UndeclaredUseCase::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/UndeclaredUseCase/');

        new CheckUseCasesDeclarePermissionsPass()->process($container);
    }

    public function testItAcceptsUseCasesThatDeclareTheirPermissions(): void
    {
        $container = $this->containerWith(ViewInvoiceUseCase::class, FinalizeInvoiceUseCase::class);

        new CheckUseCasesDeclarePermissionsPass()->process($container);

        self::assertTrue($container->hasDefinition(ViewInvoiceUseCase::class));
    }

    /**
     * Toutes les fautes d'un coup : sans quoi il faudrait autant de compilations que de cas
     * d'usage à corriger.
     */
    public function testItNamesEveryOffenderAtOnce(): void
    {
        $container = $this->containerWith(UndeclaredUseCase::class, ViewInvoiceUseCase::class);
        $container->register('second_offender', UndeclaredUseCase::class)->addTag(Tag::USE_CASE);

        try {
            new CheckUseCasesDeclarePermissionsPass()->process($container);
            self::fail('Un cas d\'usage sans droit déclaré doit être refusé.');
        } catch (LogicException $refusal) {
            self::assertSame(2, substr_count($refusal->getMessage(), UndeclaredUseCase::class));
        }
    }

    /**
     * Un service tagué qui ne serait pas un cas d'usage est laissé de côté : le contrôle ne
     * juge que ce qu'il est chargé de juger.
     */
    public function testItIgnoresATaggedServiceThatIsNotAUseCase(): void
    {
        $container = new ContainerBuilder();
        $container->register('intruder', \stdClass::class)->addTag(Tag::USE_CASE);

        new CheckUseCasesDeclarePermissionsPass()->process($container);

        self::assertTrue($container->hasDefinition('intruder'));
    }

    /**
     * Un cas d'usage que rien n'a tagué échappe au contrôle. C'est la limite du dispositif, et
     * elle vaut d'être écrite : la seule façon d'y parvenir est de soustraire le service à
     * l'autoconfiguration, geste explicite qu'une application peut surveiller de son côté.
     */
    public function testAnUntaggedUseCaseEscapesTheCheck(): void
    {
        $container = new ContainerBuilder();
        $container->register(UndeclaredUseCase::class, UndeclaredUseCase::class);

        new CheckUseCasesDeclarePermissionsPass()->process($container);

        self::assertTrue($container->hasDefinition(UndeclaredUseCase::class));
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
}
