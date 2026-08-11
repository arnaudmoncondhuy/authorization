<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Un second modèle qui se prononce sur les mêmes identités qu'un premier.
 *
 * La faute est vraisemblable : deux modèles de droits arrivent l'un après l'autre, chacun avec
 * son voter, et personne ne relit les deux `supports()` côte à côte. Sous la stratégie
 * « affirmative », le recouvrement n'aboutit pas au plus strict des deux mais au plus
 * permissif — celui-ci accorde à qui n'a rien.
 *
 * @extends Voter<string, mixed>
 */
final class OverlappingPermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'fixture.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return true;
    }
}
