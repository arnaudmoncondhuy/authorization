<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

use ArnaudMoncondhuy\Authorization\DependencyInjection\CheckOnlyUseCasesDeclarePermissionsPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\CheckSurfacesDelegateToUseCasesPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\CheckUseCasesDeclarePermissionsPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\RegisterPermissionCatalogPass;
use ArnaudMoncondhuy\Authorization\DependencyInjection\Tag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Le point de montage du paquet.
 *
 * Reste à la racine de `src/` : {@see AbstractBundle::getPath()} calcule le chemin du paquet
 * en remontant de deux dossiers depuis ce fichier, et c'est ce qui rend `../config/` juste.
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
    }

    /**
     * Les deux adaptateurs sont facultatifs, et chacun est conditionné à ce dont il dépend :
     * le paquet doit s'installer dans une application sans pare-feu comme dans une application
     * sans HTTP.
     *
     * @param array<array-key, mixed> $config le paquet n'expose aucune configuration
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['SecurityBundle'])) {
            $configurator->import('../config/security.php');

            // Les deux commandes interrogent le contrôle d'accès : sans pare-feu, elles
            // n'auraient rien à examiner.
            if (class_exists(Command::class)) {
                $configurator->import('../config/console.php');
            }
        }

        if (class_exists(ExceptionEvent::class)) {
            $configurator->import('../config/http_kernel.php');
        }
    }
}
