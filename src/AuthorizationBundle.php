<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

use ArnaudMoncondhuy\Authorization\DependencyInjection\CheckOnlyUseCasesDeclarePermissionsPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\CheckSurfacesDelegateToUseCasesPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\CheckUseCasesDeclarePermissionsPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\RefuseUserAuthorizerWithoutProviderPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\RegisterPermissionCatalogPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\Tag;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Le point de montage du paquet.
 *
 * Reste à la racine de `src/` : {@see AbstractBundle::getPath()} calcule le chemin du paquet
 * en remontant de deux dossiers depuis ce fichier, et c'est ce qui rend `../config/` juste.
 *
 * Celle de `HttpKernel\Bundle\` et non celle de `DependencyInjection\Kernel\`, qui lui est
 * pourtant identique en 8.1 — la seconde n'existe pas avant. C'est la seule classe du paquet
 * qui décidait de la version minimale de Symfony, et la prendre ici ouvre la 7.4 LTS, où
 * tourne la majorité des applications en production. Tout le reste de ce que le paquet cite,
 * `UserAuthorizationCheckerInterface` compris, existe depuis la 7.3.
 */
final class AuthorizationBundle extends AbstractBundle
{
    /**
     * Le tag et les passes se posent ici, et nulle part ailleurs : la construction de
     * l'extension refuse l'un comme l'autre.
     *
     * L'autoconfiguration se résout à la priorité 100 des passes de pré-optimisation, les
     * nôtres à la priorité par défaut : le tag est donc déjà posé quand elles regardent.
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerForAutoconfiguration(UseCase::class)
            ->addTag(Tag::USE_CASE);

        $container->addCompilerPass(new CheckUseCasesDeclarePermissionsPass());
        $container->addCompilerPass(new CheckOnlyUseCasesDeclarePermissionsPass());
        $container->addCompilerPass(new CheckSurfacesDelegateToUseCasesPass());
        $container->addCompilerPass(new RegisterPermissionCatalogPass());

        // Celle-ci ne juge aucun code de l'application : elle constate qu'un adaptateur du
        // paquet n'aurait pas de quoi répondre. Elle vit ici et non dans `loadExtension()`
        // parce qu'elle lit ce que SecurityExtension déclare, et que l'ordre de chargement des
        // extensions ne se commande pas.
        $container->addCompilerPass(new RefuseUserAuthorizerWithoutProviderPass());
    }

    /**
     * La seule clé que le paquet expose, et elle ne sert qu'à restreindre : rien n'est à écrire
     * pour monter le dispositif.
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('user_provider')
                    ->defaultNull()
                    ->cannotBeEmpty()
                    ->info(
                        'Le fournisseur de comptes où chercher le tiers que `UserAuthorizer` désigne par son '
                        .'identifiant. Absente, la recherche parcourt tous ceux que `security.providers` déclare.'
                    )
                ->end()
            ->end()
        ;
    }

    /**
     * Les deux adaptateurs sont facultatifs, et chacun est conditionné à ce dont il dépend :
     * le paquet doit s'installer dans une application sans pare-feu comme dans une application
     * sans HTTP.
     *
     * @param array<array-key, mixed> $config la configuration du bundle, telle que
     *                                        {@see self::configure()} la décrit
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        // L'inventaire ne réclame que le catalogue, présent partout : une application sans
        // pare-feu garde le droit de savoir ce que son code exige.
        if (class_exists(Command::class)) {
            $configurator->import('../config/console.php');
        }

        if (isset($bundles['SecurityBundle'])) {
            $configurator->import('../config/security.php');

            // L'examen des voters, que le docteur et le panneau se partagent. Il n'a de sens
            // qu'ici : sans pare-feu, il n'y a pas de voter à interroger.
            $configurator->import('../config/voters.php');

            // La clé absente laisse l'adaptateur sur la chaîne de tous les fournisseurs, qui est
            // ce que `config/security.php` lui donne.
            $provider = $config['user_provider'] ?? null;

            if (\is_string($provider)) {
                $this->searchAccountsIn($container, $provider);
            }

            // Le docteur, lui, interroge le contrôle d'accès : sans pare-feu, il n'aurait
            // rien à examiner.
            if (class_exists(Command::class)) {
                $configurator->import('../config/doctor.php');
            }
        }

        if (class_exists(ExceptionEvent::class)) {
            $configurator->import('../config/http_kernel.php');
        }
    }

    /**
     * Restreint la recherche d'un compte au seul fournisseur que l'application nomme.
     *
     * La définition est jointe par l'alias du contrat et non par le nom d'un adaptateur, et
     * l'argument est reconnu à ce qu'il désigne et non à son rang : une application qui aurait
     * substitué le sien reçoit le même traitement.
     */
    private function searchAccountsIn(ContainerBuilder $container, string $provider): void
    {
        $definition = $container->findDefinition(UserAuthorizer::class);

        foreach ($definition->getArguments() as $index => $argument) {
            if ($argument instanceof Reference
                && RefuseUserAuthorizerWithoutProviderPass::USER_PROVIDERS_SERVICE === (string) $argument) {
                $definition->replaceArgument($index, new Reference($provider));
            }
        }

        $container->setParameter(RefuseUserAuthorizerWithoutProviderPass::ON_BEHALF_PROVIDER_PARAMETER, $provider);
    }
}
