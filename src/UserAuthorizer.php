<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * Ce qu'un autre que l'appelant a le droit de faire.
 *
 * {@see Authorizer} répond toujours pour l'appelant courant, et c'est ce qui le rend sûr. Trois
 * questions n'entrent pourtant pas dans cette forme :
 *
 * - ne pas notifier quelqu'un sur un module auquel il a perdu l'accès ;
 * - montrer à un administrateur ce qu'une personne obtiendra ;
 * - vérifier, avant d'accorder, ce qu'un compte détient déjà.
 *
 * Sans ce contrat, il faut réimplémenter la décision hors session — et deux copies d'une même
 * règle finissent par diverger, ce que tout ce paquet existe pour empêcher.
 *
 * **Il ne sait que répondre.** Aucune méthode ne lève, et il n'existe pas de pendant à
 * {@see Authorizer::require()} : savoir ce qu'un tiers peut faire n'est pas l'autoriser à le
 * faire, et confondre les deux transformerait une lecture en usurpation.
 *
 * L'appelant est désigné par son identifiant, pas par un objet : c'est ce qui garde ce contrat
 * en PHP nu, donc citable depuis un domaine pur.
 */
interface UserAuthorizer
{
    /**
     * Rend `false` pour un identifiant qu'aucun compte ne porte : sans compte, aucun droit.
     *
     * @param non-empty-string $userIdentifier
     */
    public function can(string $userIdentifier, Permission $permission): bool;
}
