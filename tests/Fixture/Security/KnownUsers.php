<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * L'annuaire d'une application, réduit à une liste d'identifiants connus.
 *
 * @implements UserProviderInterface<InMemoryUser>
 */
final readonly class KnownUsers implements UserProviderInterface
{
    /** @var list<string> */
    private array $identifiers;

    public function __construct(string ...$identifiers)
    {
        $this->identifiers = array_values($identifiers);
    }

    public function loadUserByIdentifier(string $identifier): InMemoryUser
    {
        if (!\in_array($identifier, $this->identifiers, true)) {
            throw new UserNotFoundException(\sprintf('Aucun compte ne porte « %s ».', $identifier));
        }

        return new InMemoryUser($identifier, null, ['ROLE_USER']);
    }

    public function refreshUser(UserInterface $user): InMemoryUser
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return InMemoryUser::class === $class;
    }
}
