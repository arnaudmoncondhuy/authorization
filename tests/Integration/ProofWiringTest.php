<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Integration;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\DependencyInjection\RefuseProofWithoutJudgePass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\RegisterPermissionCatalogPass;
use ArnaudMoncondhuy\Authorization\InsufficientProof;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Proof;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\GrantingPermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\ProvenIdentity;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\InvoiceBook;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\RevealInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Kernel\AuthorizationTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * L'exigence de preuve, montée dans une application qui démarre.
 *
 * Ce que les tests unitaires ne peuvent pas dire : que le refus tombe à la compilation, et
 * qu'il tombe seulement là où il doit. Une passe examinée à la main resterait verte même si
 * plus rien ne l'enregistrait.
 */
final class ProofWiringTest extends TestCase
{
    private ?AuthorizationTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * La garantie du pont, et la seule qui compte : une exigence que personne ne saurait juger
     * ne laisse pas l'application démarrer. Le jour où quelqu'un retire le paquet qui juge,
     * c'est ceci qui l'arrête — et non une page qui s'ouvrirait sans rien demander.
     */
    public function testAProofDemandedWithoutAJudgeStopsTheCompilation(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/fixture\.invoice\.backdate \(recent\)/');

        $this->boot([RevealInvoiceUseCase::class]);
    }

    /** Le message nomme ce qui manque et ce qu'il faut faire, pas seulement la faute. */
    public function testTheRefusalNamesTheContractAndWhatToDo(): void
    {
        try {
            $this->boot([RevealInvoiceUseCase::class]);
            self::fail('La compilation aurait dû s\'arrêter.');
        } catch (LogicException $refusal) {
            self::assertStringContainsString('ProofOfIdentity', $refusal->getMessage());
            self::assertStringContainsString('authentication-policy', $refusal->getMessage());
        }
    }

    /** Une application qui n'exige aucune preuve se comporte comme avant, juge ou pas. */
    public function testAnApplicationThatDemandsNoProofBootsWithoutAJudge(): void
    {
        $container = $this->boot([ViewInvoiceUseCase::class, InvoiceBook::class])->getContainer();

        self::assertSame([], $container->getParameter(RegisterPermissionCatalogPass::REQUIRED_PROOFS_PARAMETER));
        self::assertNull($container->getParameter(RefuseProofWithoutJudgePass::JUDGE_PARAMETER));
    }

    public function testAJudgeLetsTheApplicationDemandProofs(): void
    {
        $container = $this->boot([RevealInvoiceUseCase::class], judge: ProvenIdentity::class)->getContainer();

        self::assertSame(
            ['fixture.invoice.backdate' => 'recent', 'fixture.invoice.view' => 'strong'],
            $container->getParameter(RegisterPermissionCatalogPass::REQUIRED_PROOFS_PARAMETER),
        );
        self::assertSame(ProvenIdentity::class, $container->getParameter(RefuseProofWithoutJudgePass::JUDGE_PARAMETER));
    }

    /**
     * Le câblage complet, jusqu'à ce qu'un cas d'usage reçoit : l'adaptateur consulte le
     * catalogue et interroge le juge. Un voter qui accorde tout est monté pour cela — tant que
     * le droit manque, c'est lui qu'on se voit opposer, et le détour ne se joue jamais.
     */
    public function testTheAdapterOpposesTheDetourItWasWiredFor(): void
    {
        $container = $this
            ->boot([RevealInvoiceUseCase::class, GrantingPermissionVoter::class], judge: ProvenIdentity::class)
            ->getContainer();

        /** @var PermissionCatalog $catalog */
        $catalog = $container->get(PermissionCatalog::class);
        self::assertSame(Proof::Recent, $catalog->proofFor('fixture.invoice.backdate'));

        /** @var Authorizer $access */
        $access = $container->get(Authorizer::class);

        $this->expectException(InsufficientProof::class);

        // Le droit est accordé par le voter de la fixture : ce qui manque est l'identité, que
        // le juge ne constate à aucun niveau — le cas de qui vient d'entrer avec son seul mot
        // de passe.
        $access->require(InvoicePermission::Backdate);
    }

    /**
     * @param list<class-string> $services
     * @param ?class-string      $judge
     */
    private function boot(array $services, ?string $judge = null): AuthorizationTestKernel
    {
        $this->kernel?->shutdown();

        $this->kernel = new AuthorizationTestKernel($services, judge: $judge);
        $this->kernel->boot();

        return $this->kernel;
    }
}
