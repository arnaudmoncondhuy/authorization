<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\MissingConfirmation;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Le filet, sous le refus qu'un cas d'usage oppose faute de confirmation.
 *
 * Ce refus-là n'a pas d'écran à lui : la confirmation voyage avec la requête qui pose l'acte,
 * et c'est donc la surface qui l'a demandée qui sait la redemander — en réaffichant son propre
 * formulaire, avec le champ et ce qui ne va pas. Aucun paquet ne peut le faire à sa place sans
 * connaître son écran.
 *
 * Reste ce qui arrive quand personne ne l'a fait. Sans cette traduction, l'exception remonte
 * et la surface rend une erreur du serveur : l'acte ne part pas, mais rien ne le dit. Ceci
 * ferme proprement — 422 plutôt que 403, parce que ce n'est pas un droit qui manque mais une
 * pièce de la requête, et qu'un refus qui se trompe de cause fait chercher au mauvais endroit.
 *
 * Enregistré seulement là où `symfony/http-kernel` est installé. Une application qui préfère
 * son propre écouteur retire le service de son côté.
 */
final readonly class MissingConfirmationListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $refusal = $event->getThrowable();

        if (!$refusal instanceof MissingConfirmation) {
            return;
        }

        $event->setThrowable(new UnprocessableEntityHttpException($refusal->getMessage(), $refusal));
    }
}
