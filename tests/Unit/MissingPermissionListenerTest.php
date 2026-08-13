<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Bridge\MissingPermissionListener;
use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * La traduction d'un refus du métier en refus HTTP, jouée sur l'événement réel.
 *
 * Les deux directions comptent autant : un refus non traduit s'affiche en panne du serveur, et
 * une panne traduite en refus maquillerait une erreur en décision d'autorisation.
 */
final class MissingPermissionListenerTest extends TestCase
{
    public function testARefusalBecomesAnHttpAccessDenial(): void
    {
        $refusal = MissingPermission::of(InvoicePermission::View);
        $event = $this->eventCarrying($refusal);

        new MissingPermissionListener()($event);

        $translated = $event->getThrowable();
        self::assertInstanceOf(AccessDeniedHttpException::class, $translated);
        self::assertSame(Response::HTTP_FORBIDDEN, $translated->getStatusCode());
    }

    /**
     * Le droit manquant survit à la traduction : la cause reste l'exception du métier, et son
     * message — qui nomme le droit — est celui que les gabarits d'erreur mettent en forme.
     */
    public function testTheTranslationKeepsTheRefusalAndItsMessage(): void
    {
        $refusal = MissingPermission::of(InvoicePermission::View);
        $event = $this->eventCarrying($refusal);

        new MissingPermissionListener()($event);

        $translated = $event->getThrowable();
        self::assertSame($refusal, $translated->getPrevious());
        self::assertSame($refusal->getMessage(), $translated->getMessage());
    }

    public function testAnotherFailureIsLeftUntouched(): void
    {
        $failure = new \RuntimeException('La base ne répond plus.');
        $event = $this->eventCarrying($failure);

        new MissingPermissionListener()($event);

        self::assertSame($failure, $event->getThrowable());
    }

    private function eventCarrying(\Throwable $failure): ExceptionEvent
    {
        $kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };

        return new ExceptionEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $failure);
    }
}
