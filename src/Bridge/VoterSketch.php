<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Permission;

/**
 * Le voter qui manque, écrit, pour n'avoir plus qu'à décider.
 *
 * Le nom et l'espace de noms y sont une proposition ; les identités, non — le squelette ne
 * porte que celles qui manquent de juge, parce que reprendre l'énumération entière donnerait un
 * second juge à celles qui en ont déjà un, et que sous `affirmative` un recouvrement élargit
 * les droits.
 *
 * Un squelette par contexte, le contexte étant l'énumération qui déclare le droit : c'est le
 * découpage que le paquet demande déjà, et deux contextes dans un même `supports()` feraient
 * d'un droit de facturation l'affaire du voter des stocks.
 *
 * Il est **rendu, jamais écrit sur le disque**, et il n'accorde rien : quel voter prend en
 * charge quel droit, et qui l'obtient, sont des décisions qui appartiennent au projet.
 */
final readonly class VoterSketch
{
    /**
     * @param list<Permission> $unjudged
     *
     * @return array<string, string> le chemin proposé, et le code qui va dedans
     */
    public function of(array $unjudged): array
    {
        /** @var array<class-string, list<string>> $contexts */
        $contexts = [];

        foreach ($unjudged as $permission) {
            $contexts[$permission::class][] = $permission->id();
        }

        $sketches = [];

        foreach ($contexts as $context => $ids) {
            $name = $this->voterName($context);
            $sketches[\sprintf('src/Security/%s.php', $name)] = $this->sketchOf($name, $ids);
        }

        return $sketches;
    }

    /**
     * Le refus explicite plutôt que l'abstention, aux deux endroits où il compte : le jeton
     * sans utilisateur est celui d'une requête anonyme, et une abstention y fermerait aussi le
     * verbe à toute surface sans session ; la règle laissée à écrire refuse, parce qu'un
     * squelette qui accorderait ouvrirait le droit à la seconde où il est collé.
     *
     * @param list<string> $ids
     */
    private function sketchOf(string $name, array $ids): string
    {
        $listed = implode("\n", array_map(
            static fn (string $id): string => \sprintf("            '%s',", $id),
            $ids,
        ));

        return strtr(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Security;

            use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
            use Symfony\Component\Security\Core\Authorization\Voter\Vote;
            use Symfony\Component\Security\Core\Authorization\Voter\Voter;
            use Symfony\Component\Security\Core\User\UserInterface;

            /** @extends Voter<string, mixed> */
            final class {name} extends Voter
            {
                protected function supports(string $attribute, mixed $subject): bool
                {
                    return \in_array($attribute, [
            {ids}
                    ], true);
                }

                protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
                {
                    // Le jeton d'une requête anonyme ne porte pas d'utilisateur. Refuser, plutôt
                    // que s'abstenir : une abstention fermerait aussi le verbe aux surfaces sans
                    // session — console, worker, tâche planifiée.
                    if (!$token->getUser() instanceof UserInterface) {
                        return false;
                    }

                    // À écrire : qui détient ce droit. Tel quel, il reste refusé à tout le monde
                    // — mais il a désormais un juge, et le docteur ne le nommera plus.
                    return false;
                }
            }
            PHP, ['{name}' => $name, '{ids}' => $listed]);
    }

    /**
     * « InvoicePermission » donne « InvoiceVoter ». Une proposition, et rien de plus : le
     * paquet ne connaît ni l'arborescence de l'application ni ses conventions de nommage.
     *
     * @param class-string $context
     */
    private function voterName(string $context): string
    {
        $parts = explode('\\', $context);
        $short = end($parts);

        // Une énumération nommée « Permission » tout court ne laisserait rien à préfixer.
        $stem = preg_replace('/Permissions?$/', '', $short);

        return ('' === $stem || null === $stem ? $short : $stem).'Voter';
    }
}
