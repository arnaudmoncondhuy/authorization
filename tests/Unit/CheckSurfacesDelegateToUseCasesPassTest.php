<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\CallsNoUseCase;
use ArnaudMoncondhuy\Authorization\DependencyInjection\CheckSurfacesDelegateToUseCasesPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\Tag;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Web\DelegatingController;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Web\DirectController;
use ArnaudMoncondhuy\Authorization\UseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Ce que le quatrième refus attrape, et que les trois autres laissent passer.
 *
 * Une porte qui n'appelle aucun verbe métier ne déclare aucun droit et n'en réclame aucun : à
 * ce titre, elle est irréprochable pour les trois autres contrôles. C'est pourtant par là qu'un
 * inconnu sans compte peut agir.
 */
final class CheckSurfacesDelegateToUseCasesPassTest extends TestCase
{
    public function testItRefusesASurfaceThatCallsNoUseCase(): void
    {
        $container = $this->containerWith(DirectController::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/DirectController/');

        new CheckSurfacesDelegateToUseCasesPass()->process($container);
    }

    public function testItAcceptsASurfaceThatCallsOne(): void
    {
        $container = $this->containerWith(DelegatingController::class);
        $container->register(ViewInvoiceUseCase::class, ViewInvoiceUseCase::class)->addTag(Tag::USE_CASE);

        new CheckSurfacesDelegateToUseCasesPass()->process($container);

        self::assertTrue($container->hasDefinition(DelegatingController::class));
    }

    /**
     * Un verbe reçu par une abstraction de l'application : c'est ce que le conteneur injecte
     * pour ce type qui en décide, et l'alias désigne bien un cas d'usage.
     */
    public function testAVerbReceivedThroughAnAliasedAbstractionIsSeen(): void
    {
        $container = new ContainerBuilder();
        $container->register(FinalizingUseCase::class, FinalizingUseCase::class)->addTag(Tag::USE_CASE);
        $container->setAlias(FinalizeInvoice::class, FinalizingUseCase::class);
        $container->register(AliasReceivingController::class, AliasReceivingController::class)
            ->addTag('controller.service_arguments');

        new CheckSurfacesDelegateToUseCasesPass()->process($container);

        self::assertTrue($container->hasDefinition(AliasReceivingController::class));
    }

    /**
     * Un supertype partagé n'est pas un verbe reçu. Un cas d'usage implémente `\Stringable`
     * quelque part ; une porte qui reçoit `\Stringable` n'en reçoit pas un verbe pour autant,
     * puisque le conteneur n'injecte rien pour ce type-là.
     */
    public function testASharedSupertypeDoesNotCountAsAVerb(): void
    {
        $container = new ContainerBuilder();
        $container->register(SluggedUseCase::class, SluggedUseCase::class)->addTag(Tag::USE_CASE);
        $container->register(StringReceivingController::class, StringReceivingController::class)
            ->addTag('controller.service_arguments');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/StringReceivingController/');

        new CheckSurfacesDelegateToUseCasesPass()->process($container);
    }

    /**
     * La dispense se pose sur la classe et porte sa raison. Une liste tenue ailleurs se périme
     * en silence ; celle-ci se lit au-dessus de ce qu'elle dispense.
     */
    public function testAnExemptedSurfaceIsLeftAlone(): void
    {
        $container = $this->containerWith(RedirectingController::class);

        new CheckSurfacesDelegateToUseCasesPass()->process($container);

        self::assertTrue($container->hasDefinition(RedirectingController::class));
    }

    /**
     * Les trois marques que Symfony pose lui-même, et c'est ce qui dispense d'une liste : une
     * porte ajoutée demain est examinée sans que personne ait rien déclaré.
     */
    public function testEveryFrameworkSurfaceTagIsExamined(): void
    {
        foreach (['controller.service_arguments', 'console.command', 'messenger.message_handler'] as $tag) {
            $container = new ContainerBuilder();
            $container->register(DirectController::class, DirectController::class)->addTag($tag);

            try {
                new CheckSurfacesDelegateToUseCasesPass()->process($container);
                self::fail(\sprintf('Une porte marquée « %s » doit être examinée.', $tag));
            } catch (LogicException $refusal) {
                self::assertStringContainsString('DirectController', $refusal->getMessage());
            }
        }
    }

    /**
     * Une application ouvre parfois une porte d'un autre genre — un outil pour un assistant,
     * un point d'entrée JSON-RPC. Elle la fait examiner en nommant son propre tag.
     */
    public function testAnApplicationCanNameItsOwnSurfaceTag(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(CheckSurfacesDelegateToUseCasesPass::EXTRA_TAGS_PARAMETER, ['app.tool']);
        $container->register(DirectController::class, DirectController::class)->addTag('app.tool');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/DirectController/');

        new CheckSurfacesDelegateToUseCasesPass()->process($container);
    }

    /**
     * Une porte livrée par une dépendance n'est pas la nôtre à gouverner : le framework porte
     * lui-même des dizaines de commandes, et leur demander d'appeler nos verbes n'aurait aucun
     * sens.
     */
    public function testASurfaceFromADependencyIsLeftAlone(): void
    {
        $container = new ContainerBuilder();
        $container->register('vendor_command', \Symfony\Bundle\FrameworkBundle\Command\AboutCommand::class)
            ->addTag('console.command');

        new CheckSurfacesDelegateToUseCasesPass()->process($container);

        self::assertTrue($container->hasDefinition('vendor_command'));
    }

    /**
     * Le noyau n'est pas une porte, c'est l'application. `MicroKernelTrait` l'inscrit comme
     * contrôleur pour qu'une route puisse pointer sur l'une de ses méthodes ; le juger
     * refuserait de compiler à peu près toute application Symfony.
     */
    public function testTheKernelIsNotASurface(): void
    {
        $container = new ContainerBuilder();
        $container->register('kernel', SurfacelessKernel::class)->addTag('controller.service_arguments');

        new CheckSurfacesDelegateToUseCasesPass()->process($container);

        self::assertTrue($container->hasDefinition('kernel'));
    }

    /** @param class-string ...$classes */
    private function containerWith(string ...$classes): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ($classes as $class) {
            $container->register($class, $class)->addTag('controller.service_arguments');
        }

        return $container;
    }
}

/**
 * Une porte qui n'appelle rien, et qui le dit.
 */
#[CallsNoUseCase('Redirige vers le tableau de bord, sans lire aucune donnée.')]
final class RedirectingController
{
    public function __invoke(): string
    {
        return '/';
    }
}

/**
 * Le verbe tel qu'une porte le nomme : une abstraction de l'application, que le conteneur
 * rapporte au cas d'usage par un alias.
 */
interface FinalizeInvoice
{
    public function __invoke(string $number): void;
}

/**
 * Le cas d'usage derrière l'abstraction.
 */
final class FinalizingUseCase implements UseCase, FinalizeInvoice
{
    public function __invoke(string $number): void
    {
    }
}

/**
 * Une porte qui reçoit le verbe sous le nom de l'abstraction, pas de la classe.
 */
final readonly class AliasReceivingController
{
    public function __construct(private FinalizeInvoice $finalize)
    {
    }

    public function __invoke(string $number): void
    {
        ($this->finalize)($number);
    }
}

/**
 * Un cas d'usage qui, par ailleurs, sait s'écrire — et partage donc un supertype avec tout ce
 * qui le sait aussi.
 */
final class SluggedUseCase implements UseCase, \Stringable
{
    public function __invoke(string $number): void
    {
    }

    public function __toString(): string
    {
        return 'facture';
    }
}

/**
 * Une porte qui reçoit une chaîne affichable, et rien d'autre.
 */
final class StringReceivingController
{
    public function __invoke(\Stringable $slug): string
    {
        return (string) $slug;
    }
}

/**
 * Un noyau d'application, inscrit comme contrôleur par le trait de Symfony.
 */
final class SurfacelessKernel extends \Symfony\Component\HttpKernel\Kernel
{
    /**
     * Symfony 8.1 a déplacé l'interface des bundles ; `HttpKernel\Kernel` annonce encore
     * l'ancienne, dépréciée.
     *
     * @return iterable<\Symfony\Component\DependencyInjection\Kernel\BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [];
    }

    public function registerContainerConfiguration(\Symfony\Component\Config\Loader\LoaderInterface $loader): void
    {
    }
}
