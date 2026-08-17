<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\DependencyInjection\RegisterPermissionCatalogPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\Tag;
use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Proof;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\StockPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\CollidingUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\DoublingUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\FinalizeInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\RevealInvoiceUseCase;
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

    /**
     * La collision se juge sur la valeur et non sur la classe : deux cas d'une même
     * énumération qui rendent la même identité désignent deux droits distincts sous un seul
     * nom. Comparer les classes les laisserait passer, l'un écraserait l'autre dans
     * l'inventaire, et accorder ce nom ouvrirait les deux verbes.
     */
    public function testItRefusesTwoCasesOfOneEnumerationSharingAnIdentity(): void
    {
        $container = $this->containerWith(DoublingUseCase::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/fixture\.doubled/');

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

    /**
     * L'exigence de preuve vient de la même déclaration que le droit, et se range à côté de
     * lui : sans cela, il faudrait relire les attributs à chaque demande.
     */
    public function testItGathersTheProofEachPermissionDemands(): void
    {
        $container = $this->containerWith(RevealInvoiceUseCase::class);

        new RegisterPermissionCatalogPass()->process($container);

        $catalog = $this->catalogOf($container);

        self::assertSame(Proof::Strong, $catalog->proofFor('fixture.invoice.view'));
        self::assertSame(Proof::Recent, $catalog->proofFor('fixture.invoice.backdate'));
    }

    /** Un droit qu'aucune déclaration ne relève n'exige rien de plus que d'être détenu. */
    public function testAPermissionWithoutADeclaredProofDemandsNone(): void
    {
        $container = $this->containerWith(ViewInvoiceUseCase::class);

        new RegisterPermissionCatalogPass()->process($container);

        self::assertSame(Proof::None, $this->catalogOf($container)->proofFor('fixture.invoice.view'));
        self::assertSame([], $this->catalogOf($container)->proofs());
    }

    /**
     * Deux cas d'usage peuvent porter le même droit sans courir le même risque. Retenir le plus
     * faible ferait de l'ajout d'un cas d'usage laxiste l'affaiblissement silencieux d'un droit
     * déjà protégé.
     */
    public function testThePermissionDemandedTwiceKeepsTheStrongestProof(): void
    {
        $container = $this->containerWith(ViewInvoiceUseCase::class, RevealInvoiceUseCase::class);

        new RegisterPermissionCatalogPass()->process($container);

        self::assertSame(Proof::Strong, $this->catalogOf($container)->proofFor('fixture.invoice.view'));
    }

    /**
     * La liste est posée même vide : son absence signifierait que la passe n'a pas tourné, ce
     * qui ne se distingue pas de « rien n'exige de preuve » — et le refus qui la lit doit
     * pouvoir faire la différence.
     */
    public function testTheDemandedProofsArePublishedAsAParameter(): void
    {
        $container = $this->containerWith(RevealInvoiceUseCase::class);

        new RegisterPermissionCatalogPass()->process($container);

        self::assertSame(
            ['fixture.invoice.backdate' => 'recent', 'fixture.invoice.view' => 'strong'],
            $container->getParameter(RegisterPermissionCatalogPass::REQUIRED_PROOFS_PARAMETER),
        );

        $empty = new ContainerBuilder();
        new RegisterPermissionCatalogPass()->process($empty);

        self::assertSame([], $empty->getParameter(RegisterPermissionCatalogPass::REQUIRED_PROOFS_PARAMETER));
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
        $definition = $container->getDefinition(PermissionCatalog::class);

        /** @var list<Permission> $permissions */
        $permissions = $definition->getArgument(0);
        /** @var array<string, Proof> $proofs */
        $proofs = $definition->getArgument(1);

        return new PermissionCatalog($permissions, $proofs);
    }
}
