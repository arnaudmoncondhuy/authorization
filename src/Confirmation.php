<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * À quel point l'appelant doit confirmer qu'il veut vraiment poser cet acte.
 *
 * Un droit répond « as-tu le droit ». Une preuve — {@see Proof} — répond « es-tu bien toi ».
 * Celle-ci répond à une troisième question, que ni l'une ni l'autre ne pose : « est-ce bien ce
 * que tu voulais faire ». Les trois se confondent tant qu'un acte se répare ; elles se séparent
 * devant celui qui ne se répare pas.
 *
 * **Un axe à part, et non un niveau de plus sur l'échelle des preuves.** Recopier une phrase
 * n'exige rien de ce qu'un second facteur exige, et n'en tient pas lieu : les ranger sur une
 * seule échelle ferait d'une confirmation une identité prouvée. Les deux se déclarent donc
 * ensemble et se jugent séparément — un acte peut réclamer l'une, l'autre, les deux ou aucune.
 *
 * **Ce que ça arrête, et que rien d'autre n'arrête** : le clic de trop. Une session légitime,
 * une identité prouvée à l'instant, et une main qui vise un bouton et en touche un autre. Aucun
 * facteur n'y peut rien — c'est bien la bonne personne qui clique.
 *
 * Ce paquet nomme les niveaux et n'en juge aucun : savoir ce qu'est un mot de passe ne le
 * regarde pas. Il exige seulement que quelqu'un sache répondre — {@see ConfirmationOfIntent} —
 * et refuse de compiler quand un niveau est déclaré sans personne pour en juger.
 *
 * L'échelle ne se descend pas : chaque niveau exige ce que le précédent exige, et davantage.
 *
 * Fermée. Un niveau qu'une application ajouterait n'aurait pas de juge, et le vocabulaire
 * cesserait d'être le même d'un projet à l'autre.
 */
enum Confirmation: string
{
    /**
     * Rien de plus que le droit lui-même.
     *
     * La valeur par défaut de {@see RequiresPermission}, et c'est ce qui fait qu'un code écrit
     * avant l'existence de cet axe continue de se comporter à l'identique.
     */
    case None = 'none';

    /**
     * L'acte ne part que si l'appelant a recopié ce que l'écran lui demande d'écrire.
     *
     * Arrête le clic de trop, et lui seul : quiconque tient la session peut recopier la phrase.
     * Ce n'est pas une faiblesse mais la définition — ce niveau protège d'un geste, pas d'une
     * personne.
     *
     * Se redemande à chaque acte. La phrase nomme ce qu'on va faire ; la retenir pour un quart
     * d'heure reviendrait à confirmer d'avance des actes qu'on n'a pas encore choisis.
     */
    case Typed = 'typed';

    /**
     * La phrase, et ce que l'appelant est seul à connaître.
     *
     * Arrête ce que le niveau précédent arrête, et la main qui n'est pas la bonne avec : un
     * écran laissé ouvert ne livre pas le secret de celui qui s'en est allé.
     *
     * Ce n'est pas une preuve d'identité et ne doit pas s'y substituer : un secret se vole,
     * c'est même contre cela que {@see Proof::Strong} existe. Les deux se déclarent ensemble
     * quand l'acte mérite les deux.
     */
    case Secret = 'secret';

    /**
     * Le rang sur l'échelle, croissant. Existe pour comparer, jamais pour être stocké : c'est
     * la valeur qui l'est, et un rang inséré au milieu décalerait tout le reste.
     */
    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Typed => 1,
            self::Secret => 2,
        };
    }

    /**
     * Ce niveau suffit-il à satisfaire celui qu'on exige.
     *
     * Se lit dans le sens de qui répond : « ce que j'ai confirmé couvre-t-il ce qu'on me
     * demande ».
     */
    public function satisfies(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    /**
     * Le plus exigeant des deux.
     *
     * Un même droit peut être déclaré par plusieurs cas d'usage, et rien n'oblige ceux-ci à
     * s'accorder sur le niveau. Retenir le plus fort est la seule réponse qui ne desserre
     * jamais : l'autre transformerait l'ajout d'un cas d'usage laxiste en affaiblissement
     * silencieux d'un droit déjà protégé.
     */
    public static function strongest(self $one, self $other): self
    {
        return $one->rank() >= $other->rank() ? $one : $other;
    }
}
