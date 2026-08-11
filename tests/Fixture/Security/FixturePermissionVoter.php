<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Le modèle de droits d'une application, réduit à ce qui suffit pour qu'un juge existe.
 *
 * Il n'accorde rien : ce qui est éprouvé ici n'est pas une décision, mais le fait qu'un droit
 * trouve quelqu'un pour se prononcer dessus. Un droit que personne ne juge est refusé à tout
 * le monde, ce qui ne se distingue d'un refus légitime par aucune erreur.
 *
 * @extends Voter<string, mixed>
 */
final class FixturePermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'fixture.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return false;
    }
}
