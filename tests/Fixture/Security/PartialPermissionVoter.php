<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Un voter qui ne prend en charge qu'une partie des droits de son contexte.
 *
 * C'est l'état courant d'une application qui grandit : l'énumération gagne un cas, le
 * `supports()` ne le suit pas, et les droits voisins gardent leur juge. Ce qui se joue ici est
 * ce que le squelette proposé doit contenir — les identités sans juge, et non l'énumération
 * entière, qui donnerait un second juge à celle-ci.
 *
 * @extends Voter<string, mixed>
 */
final class PartialPermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return InvoicePermission::View->id() === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return false;
    }
}
