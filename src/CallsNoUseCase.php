<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * Dispense une surface d'appeler un verbe métier, et dit pourquoi.
 *
 * Une porte qui n'appelle aucun cas d'usage ne traverse aucun contrôle de droit. C'est parfois
 * légitime — une redirection, un formulaire de connexion, une page d'accueil sans donnée — et
 * jamais anodin.
 *
 * La raison est obligatoire, et c'est le seul but de cet attribut : rendre l'exception lisible
 * à l'endroit où elle s'applique, plutôt que dans une liste tenue ailleurs. Une liste se périme
 * en silence ; celle-ci se lit au-dessus de la classe qu'elle dispense.
 *
 *     #[CallsNoUseCase('Redirige vers le tableau de bord, sans lire aucune donnée.')]
 *     final class HomeController
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class CallsNoUseCase
{
    /** @param non-empty-string $reason */
    public function __construct(public string $reason)
    {
    }
}
