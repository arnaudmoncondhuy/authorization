<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Accorde tout ce qui commence par « fixture. ».
 *
 * Le contraire de {@see FixturePermissionVoter}, et il sert à autre chose : ici on ne veut pas
 * savoir si un droit trouve un juge, mais ce qu'il advient une fois le droit acquis. C'est le
 * seul montage où l'exigence de preuve se joue pour de vrai — tant que le droit manque, c'est
 * lui qu'on se voit opposer.
 *
 * @extends Voter<string, mixed>
 */
final class GrantingPermissionVoter extends Voter
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
