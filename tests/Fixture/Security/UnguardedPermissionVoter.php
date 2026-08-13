<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Le voter que presque toutes les applications finissent par écrire : il prend en charge une
 * famille de droits, puis lit l'utilisateur sans se demander s'il y en a un.
 *
 * Ce n'est pas une faute de laboratoire. Le docteur l'interroge avec un jeton vide, qui est
 * celui d'une requête anonyme : ce qui lève ici lève aussi en production, et la surface rend
 * une erreur serveur au lieu d'un refus. Ce que ce fixture éprouve est que le diagnostic le
 * rapporte au lieu de tomber avec lui, et que les autres droits soient tout de même examinés.
 *
 * @extends Voter<string, mixed>
 */
final class UnguardedPermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'fixture.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // Aucune garde sur l'absence d'utilisateur : c'est exactement l'oubli qu'on reproduit.
        // @phpstan-ignore method.nonObject
        return [] !== $token->getUser()->getRoles();
    }
}
