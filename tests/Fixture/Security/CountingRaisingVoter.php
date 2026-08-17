<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Un voter qui lève, et qui compte combien de fois on le lui a demandé.
 *
 * Ce qu'il éprouve n'est pas le rapport mais son économie : l'examen ne réinterroge pas un
 * voter qui vient de lever. Sur une application aux dizaines de droits, le faire répéterait la
 * même exception à chaque ligne sans rien apprendre — et le panneau de la barre de debug le
 * rejoue à chaque page.
 *
 * @extends Voter<string, mixed>
 */
final class CountingRaisingVoter extends Voter
{
    public int $asked = 0;

    protected function supports(string $attribute, mixed $subject): bool
    {
        ++$this->asked;

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        throw new \RuntimeException('sans utilisateur, je ne sais pas répondre');
    }
}
