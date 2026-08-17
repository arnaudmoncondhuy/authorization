<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * À quel point l'appelant doit avoir prouvé son identité pour qu'un droit s'exerce.
 *
 * Un droit répond « as-tu le droit ». Une preuve répond « es-tu bien toi, et depuis quand ».
 * Les deux se confondent tant que le mot de passe suffit à entrer, et se séparent le jour où
 * il est volé : celui qui entre avec détient tous les droits du compte, et n'a rien prouvé.
 *
 * Ce paquet nomme les niveaux et n'en juge aucun : savoir ce qu'authentifier veut dire ne le
 * regarde pas. Il exige seulement que quelqu'un sache répondre — {@see ProofOfIdentity} — et
 * refuse de compiler quand un niveau est déclaré sans personne pour en juger.
 *
 * L'échelle ne se descend pas : chaque niveau exige ce que le précédent exige, et davantage.
 * C'est ce qui permet à un même droit déclaré par deux cas d'usage de retenir le plus exigeant
 * des deux sans que le choix ait à se discuter — un droit ne peut que se resserrer.
 *
 * Fermée. Un niveau qu'une application ajouterait n'aurait pas de juge, et le vocabulaire
 * cesserait d'être le même d'un projet à l'autre.
 */
enum Proof: string
{
    /**
     * Rien de plus que le droit lui-même.
     *
     * La valeur par défaut de {@see RequiresPermission}, et c'est ce qui fait qu'un code écrit
     * avant l'existence de cette échelle continue de se comporter à l'identique.
     */
    case None = 'none';

    /**
     * Le compte est protégé par un moyen de reconnaître son porteur, et l'a présenté pour
     * ouvrir la session en cours.
     *
     * Arrête le mot de passe volé : celui qui le détient n'a pas le téléphone. N'arrête pas la
     * session déjà ouverte — l'ordinateur laissé déverrouillé, le cookie dérobé — puisque le
     * moyen a été présenté avant et ne sera pas redemandé.
     */
    case Strong = 'strong';

    /**
     * Le moyen a été présenté il y a peu, et sera redemandé sinon.
     *
     * Arrête ce que le niveau précédent arrête, et la session abandonnée avec. C'est le seul
     * niveau qui protège le geste plutôt que l'entrée, et le seul qui coûte quelque chose à
     * celui qui travaille : à réserver aux actes qu'on ne refait pas deux fois par heure.
     */
    case Recent = 'recent';

    /**
     * Le rang sur l'échelle, croissant. Existe pour comparer, jamais pour être stocké : c'est
     * la valeur qui l'est, et un rang inséré au milieu décalerait tout le reste.
     */
    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Strong => 1,
            self::Recent => 2,
        };
    }

    /**
     * Ce niveau suffit-il à satisfaire celui qu'on exige.
     *
     * Se lit dans le sens de qui répond : « ce que j'ai prouvé couvre-t-il ce qu'on me
     * demande ». Un juge qui sait dire ce qu'il a constaté n'a rien d'autre à savoir.
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
