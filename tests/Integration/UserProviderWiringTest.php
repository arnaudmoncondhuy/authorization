<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Integration;

use ArnaudMoncondhuy\Authorization\Bridge\SecurityUserAuthorizer;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\LoadedAccountPermissionVoter;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\NotifiesOnBehalf;
use ArnaudMoncondhuy\Authorization\Tests\Kernel\AuthorizationTestKernel;
use ArnaudMoncondhuy\Authorization\UserAuthorizer;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Le contrat « répondre sur un tiers », éprouvé sur les fournisseurs de comptes que
 * l'application déclare.
 *
 * Leur nombre décide, et il ne se voit nulle part ailleurs. `SecurityExtension` ne pose l'alias
 * `UserProviderInterface` que s'il y en a **exactement un** — pas de branche pour zéro, pas de
 * branche pour deux. Un paquet qui référence cet alias s'installe donc chez son auteur, qui en
 * a un, et casse chez l'application à deux modèles de comptes, qui est le cas courant dès
 * qu'un annuaire s'ajoute à une table.
 *
 * Le noyau du dépôt n'en déclarait qu'un : aucune suite ne pouvait voir la faute.
 */
final class UserProviderWiringTest extends TestCase
{
    /**
     * Deux annuaires, un compte dans chacun : celui des personnes et celui des machines. C'est
     * la configuration d'une application dès qu'un annuaire s'ajoute à une table, et c'est
     * aussi la seule qui distingue la chaîne entière d'un fournisseur nommé.
     */
    private const array TWO_DIRECTORIES = [
        'humains' => ['memory' => ['users' => ['alice' => ['roles' => ['ROLE_USER']]]]],
        'machines' => ['memory' => ['users' => ['robot' => ['roles' => ['ROLE_SERVICE']]]]],
    ];

    /** Le nom que SecurityExtension donne au service du second annuaire. */
    private const string MACHINES = 'security.user.provider.concrete.machines';

    private ?AuthorizationTestKernel $kernel = null;

    /**
     * Le cas que le paquet ne servait pas. Deux fournisseurs, et `security.user_providers` rend
     * un `ChainUserProvider` — c'est ce nom-là qu'il fallait citer.
     */
    public function testTheContractIsWiredWhenTheApplicationHasTwoProviders(): void
    {
        $container = $this->boot(self::TWO_DIRECTORIES, NotifiesOnBehalf::class);

        self::assertInstanceOf(SecurityUserAuthorizer::class, $this->contract($container));
    }

    /**
     * Et il répond, ce qui n'est pas la même chose que d'être câblé : un compte doit être trouvé
     * quel que soit l'annuaire où il vit, y compris le second — que le fournisseur unique
     * n'aurait jamais atteint. Un identifiant qu'aucun des deux ne porte reste refusé.
     */
    public function testTheContractFindsAnAccountInEitherDirectory(): void
    {
        $contract = $this->contract($this->boot(
            self::TWO_DIRECTORIES,
            NotifiesOnBehalf::class,
            LoadedAccountPermissionVoter::class,
        ));

        self::assertTrue($contract->can('alice', InvoicePermission::View));
        self::assertTrue($contract->can('robot', InvoicePermission::View));
        self::assertFalse($contract->can('personne', InvoicePermission::View));
    }

    public function testTheContractIsWiredWhenTheApplicationHasOneProvider(): void
    {
        $container = $this->boot(['comptes' => ['memory' => null]], NotifiesOnBehalf::class);

        self::assertInstanceOf(SecurityUserAuthorizer::class, $this->contract($container));
    }

    /**
     * La porte de sortie, pour l'application qui ne veut pas de la chaîne entière : elle nomme
     * son annuaire, et la recherche s'y tient. Le compte de l'autre annuaire devient introuvable
     * — c'est exactement ce qui est demandé, et c'est ce qui prouve que la clé est lue.
     */
    public function testTheSearchNarrowsToTheProviderTheApplicationNames(): void
    {
        $contract = $this->contract($this->bootSearching(
            self::MACHINES,
            NotifiesOnBehalf::class,
            LoadedAccountPermissionVoter::class,
        ));

        self::assertTrue($contract->can('robot', InvoicePermission::View));
        self::assertFalse($contract->can('alice', InvoicePermission::View));
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

    private function contract(ContainerInterface $container): UserAuthorizer
    {
        $consumer = $container->get(NotifiesOnBehalf::class);
        self::assertInstanceOf(NotifiesOnBehalf::class, $consumer);

        return $consumer->tiers;
    }

    /**
     * @param array<string, mixed> $providers
     * @param class-string         ...$services
     */
    private function boot(array $providers, string ...$services): ContainerInterface
    {
        return $this->start(new AuthorizationTestKernel(array_values($services), $providers));
    }

    /** @param class-string ...$services */
    private function bootSearching(string $userProvider, string ...$services): ContainerInterface
    {
        return $this->start(new AuthorizationTestKernel(
            array_values($services),
            self::TWO_DIRECTORIES,
            userProvider: $userProvider,
        ));
    }

    private function start(AuthorizationTestKernel $kernel): ContainerInterface
    {
        $this->kernel = $kernel;
        $kernel->boot();

        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }
}
