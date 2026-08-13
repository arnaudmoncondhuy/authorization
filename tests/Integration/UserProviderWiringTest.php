<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Integration;

use ArnaudMoncondhuy\Authorization\Bridge\SecurityUserAuthorizer;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\NotifiesOnBehalf;
use ArnaudMoncondhuy\Authorization\Tests\Kernel\AuthorizationTestKernel;
use ArnaudMoncondhuy\Authorization\UserAuthorizer;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Le contrat « répondre sur un tiers », éprouvé sur le nombre de fournisseurs de comptes que
 * l'application déclare.
 *
 * Ce nombre décide, et il ne se voit nulle part ailleurs. `SecurityExtension` ne pose l'alias
 * `UserProviderInterface` que s'il y en a **exactement un** — pas de branche pour zéro, pas de
 * branche pour deux. Un paquet qui référence cet alias s'installe donc chez son auteur, qui en
 * a un, et casse chez l'application à deux modèles de comptes, qui est le cas courant dès
 * qu'un annuaire s'ajoute à une table.
 *
 * Le noyau du dépôt n'en déclarait qu'un : aucune suite ne pouvait voir la faute.
 */
final class UserProviderWiringTest extends TestCase
{
    private ?AuthorizationTestKernel $kernel = null;

    /**
     * Le cas que le paquet ne servait pas. Deux fournisseurs, et `security.user_providers` rend
     * un `ChainUserProvider` — c'est ce nom-là qu'il fallait citer.
     */
    public function testTheContractIsWiredWhenTheApplicationHasTwoProviders(): void
    {
        $container = $this->boot(
            ['annuaire' => ['memory' => null], 'comptes' => ['memory' => null]],
            NotifiesOnBehalf::class,
        );

        $consumer = $container->get(NotifiesOnBehalf::class);
        self::assertInstanceOf(NotifiesOnBehalf::class, $consumer);
        self::assertInstanceOf(SecurityUserAuthorizer::class, $consumer->tiers);
    }

    public function testTheContractIsWiredWhenTheApplicationHasOneProvider(): void
    {
        $container = $this->boot(['comptes' => ['memory' => null]], NotifiesOnBehalf::class);

        $consumer = $container->get(NotifiesOnBehalf::class);
        self::assertInstanceOf(NotifiesOnBehalf::class, $consumer);
        self::assertInstanceOf(SecurityUserAuthorizer::class, $consumer->tiers);
    }

    /**
     * Sans fournisseur, aucun identifiant ne peut être rapporté à un compte. Le paquet ne s'en
     * mêle pas tant que personne ne demande : une application qui n'use pas du contrat n'a
     * aucune raison d'apprendre qu'elle ne pourrait pas.
     */
    public function testAnApplicationWithoutProvidersBootsWhenNobodyAsksForTheContract(): void
    {
        $container = $this->boot([]);

        self::assertFalse($container->has(UserAuthorizer::class));
    }

    /**
     * Et dès que quelqu'un demande, la compilation s'arrête sur une phrase qui dit quoi faire.
     * Sans la faute posée sur la définition, le message serait celui de Symfony — « dependency
     * on a non-existent service security.user_providers » — vrai, et illisible pour qui n'a pas
     * lu le paquet.
     */
    public function testAskingForTheContractWithoutProvidersStopsTheCompilation(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/aucun fournisseur de comptes/');
        $this->expectExceptionMessageMatches('/security\.providers/');

        $this->boot([], NotifiesOnBehalf::class);
    }

    /**
     * @param array<string, mixed> $providers
     * @param class-string         ...$services
     */
    private function boot(array $providers, string ...$services): ContainerInterface
    {
        $this->kernel = new AuthorizationTestKernel(array_values($services), $providers);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }
}
