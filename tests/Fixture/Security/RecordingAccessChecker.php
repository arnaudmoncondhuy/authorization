<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Security;

use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Le contrôle d'accès de Symfony, réduit à ce qu'on lui accorde — et qui retient ce qu'on lui
 * a demandé.
 *
 * Retenir la question compte autant qu'y répondre : ce qui doit être soumis au contrôle
 * d'accès, c'est l'identité du droit et rien d'autre. Y soumettre le nom du cas, ou l'objet,
 * ferait décider sur autre chose que ce qui est stocké et accordé.
 */
final class RecordingAccessChecker implements UserAuthorizationCheckerInterface
{
    /** @var list<array{user: string, attribute: mixed}> */
    public array $asked = [];

    /** @var list<string> */
    private readonly array $granted;

    public function __construct(string ...$granted)
    {
        $this->granted = array_values($granted);
    }

    public function isGrantedForUser(UserInterface $user, mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        $this->asked[] = ['user' => $user->getUserIdentifier(), 'attribute' => $attribute];

        return \is_string($attribute) && \in_array($attribute, $this->granted, true);
    }
}
