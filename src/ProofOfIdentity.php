<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * Ce que l'appelant courant a prouvé de son identité.
 *
 * Ce paquet ne sait pas authentifier, et n'a pas à l'apprendre : il constate qu'un droit exige
 * une preuve, et pose la question à qui la connaît. Ce qui répond est l'affaire de
 * l'application — en pratique le paquet qui tient sa politique d'authentification, qui seul
 * sait ce qu'est un second facteur et quand il a été présenté.
 *
 * **Il ne sait que répondre.** Aucune méthode ne lève, et il n'existe pas de pendant à
 * {@see Authorizer::require()} : c'est {@see Authorizer} qui refuse, parce que c'est lui qui
 * sait quel droit était en jeu et peut le dire à qui le rapporte.
 *
 * Toujours pour l'appelant courant, jamais pour un tiers. « Cette personne a-t-elle prouvé
 * récemment » n'est pas une question qu'on pose de l'extérieur : la réponse dépend d'une
 * session, et {@see UserAuthorizer} désigne quelqu'un qui n'en a pas ici.
 *
 * Sans implémentation, un droit qui exige une preuve arrête la compilation du conteneur. Le
 * défaut est de refuser : une application qui retirerait ce qui juge ne doit pas se mettre à
 * laisser passer ce qu'elle protégeait la veille.
 */
interface ProofOfIdentity
{
    /**
     * Vrai quand ce que l'appelant a prouvé couvre le niveau demandé.
     *
     * Répond vrai à {@see Proof::None} sans rien regarder : ce niveau n'exige rien, et
     * {@see Authorizer} ne pose même pas la question.
     */
    public function meets(Proof $required): bool;
}
