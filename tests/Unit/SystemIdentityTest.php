<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Scope\SystemIdentity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * La portée d'une identité que l'application se donne à elle-même.
 *
 * Ce qui se vérifie ici n'est pas qu'un droit soit accordé — un voter en décide — mais que le
 * jeton précédent soit rendu dans tous les cas. Une identité de service qui survivrait au
 * traitement contaminerait tout ce qui suit dans le même processus : un worker qui enchaîne
 * les messages, une commande qui en appelle une autre.
 */
final class SystemIdentityTest extends TestCase
{
    /**
     * Deux promesses d'un coup : le traitement voit bien l'identité donnée, et ce qu'il rend
     * remonte tel quel à l'appelant.
     */
    public function testTheWorkRunsUnderTheGivenIdentityAndItsResultComesBack(): void
    {
        $tokens = new TokenStorage();
        $system = new SystemIdentity($tokens);

        $seen = $system->run($this->identity('service'), static fn (): ?TokenInterface => $tokens->getToken());

        self::assertInstanceOf(TokenInterface::class, $seen);
        self::assertSame('service', $seen->getUserIdentifier());
    }

    public function testTheEmptinessBeforeIsRestoredAfter(): void
    {
        $tokens = new TokenStorage();
        $system = new SystemIdentity($tokens);

        $system->run($this->identity('service'), static fn (): null => null);

        self::assertNull($tokens->getToken());
    }

    /**
     * Le cas qui compte : une commande qui échoue ne doit pas laisser son identité derrière
     * elle, sans quoi le traitement suivant hériterait de droits qu'il n'a pas demandés.
     */
    public function testTheIdentityIsGivenBackEvenWhenTheWorkThrows(): void
    {
        $tokens = new TokenStorage();
        $tokens->setToken($this->identity('humain'));
        $system = new SystemIdentity($tokens);

        $reported = null;

        try {
            $system->run($this->identity('service'), static fn () => throw new \RuntimeException('le traitement a échoué'));
        } catch (\RuntimeException $failure) {
            $reported = $failure->getMessage();
        }

        self::assertSame('le traitement a échoué', $reported);

        $restored = $tokens->getToken();
        self::assertInstanceOf(TokenInterface::class, $restored);
        self::assertSame('humain', $restored->getUserIdentifier());
    }

    /**
     * Deux portées imbriquées se rendent leur jeton dans l'ordre : une commande qui en appelle
     * une autre ne se retrouve pas sous l'identité de sa fille.
     */
    public function testNestedScopesUnwindInOrder(): void
    {
        $tokens = new TokenStorage();
        $system = new SystemIdentity($tokens);

        $system->run($this->identity('exterieur'), function () use ($system, $tokens): void {
            $system->run($this->identity('interieur'), static fn (): null => null);

            $inside = $tokens->getToken();
            self::assertInstanceOf(TokenInterface::class, $inside);
            self::assertSame('exterieur', $inside->getUserIdentifier());
        });

        self::assertNull($tokens->getToken());
    }

    private function identity(string $name): TokenInterface
    {
        $user = new InMemoryUser($name, null, ['ROLE_SERVICE']);

        return new UsernamePasswordToken($user, 'system', $user->getRoles());
    }
}
