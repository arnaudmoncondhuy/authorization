<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Demande à chaque voter installé sur quels droits il se prononce.
 *
 * Elle cherche trois choses qu'aucun autre contrôle ne voit.
 *
 * **Un droit qu'aucun voter ne prend en charge** : le code l'exige, l'inventaire le propose,
 * l'écran d'attribution permet de le cocher — et il est refusé à tout le monde, administrateur
 * compris, sans qu'aucune erreur ne soit levée nulle part.
 *
 * **Un droit que plusieurs voters prennent en charge** : sous la stratégie `affirmative`, celle
 * de Symfony par défaut, il suffit qu'**un** accorde pour que l'accès passe, et les refus des
 * autres ne sont pas consultés. Deux modèles qui se recouvrent n'aboutissent donc pas au plus
 * strict des deux mais au plus permissif.
 *
 * **Un voter qui lève quand on l'interroge sans utilisateur** : l'examen se joue avec un jeton
 * vide, qui est celui d'une requête anonyme. Un voter qui y lève lèvera aussi en production, et
 * la surface rendra une erreur serveur au lieu d'un refus.
 *
 * L'examen est une lecture, jamais une décision : il soumet des identités à des voters et note
 * qui répond. Ce qu'un voter accorde ne l'intéresse pas — un voter qui refuse est un juge au
 * même titre qu'un voter qui accorde, et confondre les deux ferait passer une application
 * entière pour malade.
 */
final readonly class VoterSurvey
{
    /** @param iterable<VoterInterface> $voters */
    public function __construct(
        private PermissionCatalog $catalog,
        private iterable $voters,
    ) {
    }

    public function examine(): VoterCoverage
    {
        // Fixés une fois : la liste vient d'un itérateur du conteneur, et la reparcourir à
        // chaque droit la reconstruirait autant de fois qu'il y a de droits.
        $voters = array_values([...$this->voters]);

        $permissions = $this->catalog->all();

        /** @var array<string, list<string>> $judges */
        $judges = [];
        /** @var array<string, string> $raised */
        $raised = [];

        foreach ($permissions as $permission) {
            $judges[$permission->id()] = $this->judgesOf($permission, $voters, $raised);
        }

        return new VoterCoverage($judges, $raised, $permissions, \count($voters));
    }

    /**
     * Les voters qui se prononcent sur cette identité. Un voter qui s'abstient ne la prend pas
     * en charge : sur un voter bâti sur la classe abstraite de Symfony, c'est exact —
     * l'abstention y est la réponse lorsque `supports()` refuse. Un voter qui s'abstient pour
     * une autre raison, l'absence d'utilisateur par exemple, serait compté absent à tort.
     *
     * Un voter qui lève est retenu comme tel, et l'examen continue. Laisser filer l'exception
     * ferait tomber l'examen sur le premier voter fragile, et les droits suivants ne seraient
     * jamais regardés — un constat qui s'interrompt en dit moins qu'un constat qui rapporte. Il
     * n'est compté ni juge ni absent : ce qu'il aurait répondu reste inconnu, et c'est cela
     * qu'il faut dire.
     *
     * @param list<VoterInterface>  $voters
     * @param array<string, string> $raised recueille les voters qui ont levé, par classe
     *
     * @return list<string>
     */
    private function judgesOf(Permission $permission, array $voters, array &$raised): array
    {
        $nobody = new NullToken();
        $judges = [];

        foreach ($voters as $voter) {
            // Le premier incident suffit à disqualifier le voter : réinterroger celui qui vient
            // de lever sur les droits suivants n'apprendrait rien et allongerait le rapport.
            if (isset($raised[$voter::class])) {
                continue;
            }

            try {
                $vote = $voter->vote($nobody, null, [$permission->id()]);
            } catch (\Throwable $trouble) {
                $raised[$voter::class] = \sprintf('%s : %s', $trouble::class, $trouble->getMessage());
                continue;
            }

            if (VoterInterface::ACCESS_ABSTAIN !== $vote) {
                $judges[] = $voter::class;
            }
        }

        return $judges;
    }
}
