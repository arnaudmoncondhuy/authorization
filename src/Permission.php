<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * Un droit que l'application sait exiger.
 *
 * Le contrat se limite à une identité : c'est elle, et rien d'autre, qui traverse jusqu'au
 * contrôle d'accès. Ce qu'un droit porte par ailleurs — libellé, regroupement, niveau, valeur
 * par défaut — appartient au modèle de l'application, qui le range où il lui convient.
 *
 * Les droits se déclarent par énumération, une par contexte métier :
 *
 *     enum InvoicePermission: string implements Permission
 *     {
 *         case View = 'invoice.view';
 *         case Finalize = 'invoice.finalize';
 *
 *         public function id(): string
 *         {
 *             return $this->value;
 *         }
 *     }
 */
interface Permission
{
    /**
     * Préfixée par son contexte : deux contextes qui choisiraient la même identité
     * désigneraient le même droit, et le partageraient sans que rien ne le signale.
     *
     * Stable une fois écrite. C'est elle qu'un compte se voit accorder, donc elle survit en
     * base à la classe qui la déclare : la renommer sans reprendre les droits déjà accordés
     * les révoque en silence.
     */
    public function id(): string;
}
