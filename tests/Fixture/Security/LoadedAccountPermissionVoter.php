<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Un modèle de droits réduit à une seule question : un compte a-t-il été chargé ?
 *
 * Il rend visible **ce que le fournisseur a trouvé**, et c'est sa seule raison d'être. Sans lui,
 * `can()` répondrait faux pour un identifiant qu'aucun annuaire ne porte comme pour un compte
 * trouvé qu'aucun modèle n'accorde : les deux ne se distingueraient pas, et un contrat branché
 * sur le mauvais annuaire aurait l'air de fonctionner.
 *
 * @extends Voter<string, mixed>
 */
final class LoadedAccountPermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'fixture.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof UserInterface;
    }
}
