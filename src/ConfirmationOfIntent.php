<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * Ce que l'appelant courant a confirmé de son intention.
 *
 * Ce paquet ne sait pas recueillir une confirmation, et n'a pas à l'apprendre : il constate
 * qu'un droit en exige une, et pose la question à qui la connaît. Ce qui répond est l'affaire
 * de l'application — en pratique le paquet qui tient son authentification, qui seul sait ce
 * qu'est un mot de passe et par où une phrase recopiée est arrivée.
 *
 * **Toujours pour l'acte en cours.** Une confirmation ne voyage pas : elle accompagne la
 * requête qui pose l'acte, et ne vaut pas pour le suivant. C'est ce qui la distingue d'une
 * preuve d'identité, qui vaut le temps d'une session ou d'une fraîcheur.
 *
 * **Il ne sait que répondre.** Aucune méthode ne lève, et il n'existe pas de pendant à
 * {@see Authorizer::require()} : c'est {@see Authorizer} qui refuse, parce que c'est lui qui
 * sait quel droit était en jeu et peut le dire à qui le rapporte.
 *
 * Sans implémentation, un droit qui exige une confirmation arrête la compilation du conteneur.
 * Le défaut est de refuser : une application qui retirerait ce qui juge ne doit pas se mettre à
 * laisser passer ce qu'elle protégeait la veille.
 */
interface ConfirmationOfIntent
{
    /**
     * Vrai quand ce que l'appelant a confirmé couvre le niveau demandé.
     *
     * Répond vrai à {@see Confirmation::None} sans rien regarder : ce niveau n'exige rien, et
     * {@see Authorizer} ne pose même pas la question.
     */
    public function meets(Confirmation $required): bool;
}
