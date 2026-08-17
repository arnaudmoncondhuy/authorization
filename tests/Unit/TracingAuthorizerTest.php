<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Bridge\TracingAuthorizer;
use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\FixedAuthorizer;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\FinalizeInvoiceUseCase;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\InvoiceBook;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\ViewInvoiceUseCase;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le décorateur note, et ce qu'il se garde de changer.
 *
 * Un outil de mise au point qui modifierait la décision serait pire qu'aucun outil : il
 * ouvrirait en dev un verbe fermé en production, et l'écart ne se verrait qu'en ligne.
 */
final class TracingAuthorizerTest extends TestCase
{
    public function testItReturnsWhatTheContractAnswers(): void
    {
        $tracing = new TracingAuthorizer(new FixedAuthorizer(InvoicePermission::View));

        self::assertTrue($tracing->can(InvoicePermission::View));
        self::assertFalse($tracing->can(InvoicePermission::Finalize));
    }

    /**
     * Le refus traverse intact. C'est lui qui arrête le cas d'usage, et qu'un écouteur traduit
     * en 403 : l'avaler laisserait le verbe s'exécuter sans droit.
     */
    public function testARefusalIsNotedThenRelayed(): void
    {
        $tracing = new TracingAuthorizer(new FixedAuthorizer());

        try {
            $tracing->require(InvoicePermission::View);
            self::fail('Le refus aurait dû être relancé.');
        } catch (MissingPermission) {
        }

        self::assertSame(
            [['id' => 'fixture.invoice.view', 'kind' => 'require', 'granted' => false, 'caller' => null]],
            $tracing->calls(),
        );
    }

    public function testAGrantedDemandIsNoted(): void
    {
        $tracing = new TracingAuthorizer(new FixedAuthorizer(InvoicePermission::View));

        $tracing->require(InvoicePermission::View);

        self::assertSame(
            [['id' => 'fixture.invoice.view', 'kind' => 'require', 'granted' => true, 'caller' => null]],
            $tracing->calls(),
        );
    }

    /**
     * Une consultation se distingue d'une demande : `can()` sert à masquer un bouton, et son
     * refus est le cas courant. Les confondre ferait passer une page normale pour un incident.
     */
    public function testAConsultationIsDistinguishedFromADemand(): void
    {
        $tracing = new TracingAuthorizer(new FixedAuthorizer());

        $tracing->can(InvoicePermission::View);

        self::assertSame(['can'], array_column($tracing->calls(), 'kind'));
    }

    /**
     * Le chaînon que rien d'autre ne montre : l'appel est rattaché au verbe métier qui l'a
     * fait. Le contrôle d'accès de Symfony voit passer une identité, il ne sait pas d'où.
     */
    public function testItAttributesTheDemandToTheVerbThatMadeIt(): void
    {
        $tracing = new TracingAuthorizer(new FixedAuthorizer(InvoicePermission::View));

        (new ViewInvoiceUseCase($tracing, new InvoiceBook()))('F-1');

        self::assertSame([ViewInvoiceUseCase::class], array_column($tracing->calls(), 'caller'));
    }

    /**
     * Un verbe qui réclame deux droits les fait noter tous les deux, dans l'ordre où il les a
     * demandés — c'est l'ordre dans lequel la page s'est jouée.
     */
    public function testItKeepsTheOrderInWhichAVerbAsked(): void
    {
        $tracing = new TracingAuthorizer(new FixedAuthorizer(InvoicePermission::Finalize, InvoicePermission::Backdate));

        (new FinalizeInvoiceUseCase($tracing, new InvoiceBook()))('F-1', new \DateTimeImmutable('2026-01-01'));

        self::assertSame(['fixture.invoice.finalize', 'fixture.invoice.backdate'], array_column($tracing->calls(), 'id'));
    }

    /**
     * Un appel venu d'ailleurs qu'un verbe est rangé hors verbe, et c'est en soi une
     * information : le dispositif veut qu'un droit se réclame dans un verbe.
     */
    public function testAnAppealFromOutsideAVerbHasNoCaller(): void
    {
        $tracing = new TracingAuthorizer(new FixedAuthorizer(InvoicePermission::View));

        $tracing->can(InvoicePermission::View);

        self::assertSame([null], array_column($tracing->calls(), 'caller'));
    }

    /**
     * L'adaptateur enveloppé, et non le décorateur : c'est lui qui décide, et c'est lui que le
     * docteur comme le panneau doivent nommer.
     */
    public function testItNamesTheContractItWraps(): void
    {
        $tracing = new TracingAuthorizer(new FixedAuthorizer());

        self::assertSame(FixedAuthorizer::class, $tracing->wraps());
    }

    /** Et à travers plusieurs épaisseurs, si une application en avait ajouté une. */
    public function testItLooksThroughSeveralLayers(): void
    {
        $tracing = new TracingAuthorizer(new TracingAuthorizer(new FixedAuthorizer()));

        self::assertSame(FixedAuthorizer::class, $tracing->wraps());
    }

    /**
     * Entre deux requêtes d'un même processus — un worker, un serveur qui reste en vie — ce
     * qu'une page a demandé ne doit pas s'ajouter à ce que la suivante demande.
     */
    public function testItForgetsBetweenTwoRequests(): void
    {
        $tracing = new TracingAuthorizer(new FixedAuthorizer(InvoicePermission::View));

        $tracing->can(InvoicePermission::View);
        $tracing->reset();

        self::assertSame([], $tracing->calls());
    }
}
