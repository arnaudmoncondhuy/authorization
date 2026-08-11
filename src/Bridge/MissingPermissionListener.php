<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\MissingPermission;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Traduit en refus HTTP le refus qu'un cas d'usage oppose.
 *
 * Un cas d'usage se joue aussi hors requête, et son refus est donc une exception du métier.
 * Sans cette traduction, une page atteinte sans droit rendrait une erreur du serveur — le
 * dispositif fonctionnerait, et l'utilisateur verrait une panne.
 *
 * L'exception est remplacée plutôt que la réponse : la mise en forme d'un refus appartient à
 * l'application, qui la choisit par ses gabarits d'erreur.
 *
 * Enregistré seulement là où `symfony/http-kernel` est installé. Une application qui préfère
 * son propre écouteur retire le service de son côté.
 */
final readonly class MissingPermissionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $refusal = $event->getThrowable();

        if (!$refusal instanceof MissingPermission) {
            return;
        }

        $event->setThrowable(new AccessDeniedHttpException($refusal->getMessage(), $refusal));
    }
}
