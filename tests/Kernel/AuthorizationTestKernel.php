<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Kernel;

use ArnaudMoncondhuy\Authorization\AuthorizationBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Une application minuscule mais réelle, qui enregistre le bundle comme le ferait un projet.
 *
 * C'est le seul endroit où le paquet est éprouvé branché. Les passes examinées à la main
 * resteraient vertes même si plus rien ne les enregistrait, et le tag posé par
 * autoconfiguration n'existe pas dans un `ContainerBuilder` bâti par un test.
 *
 * Chaque jeu de services obtient son propre dossier de cache : deux noyaux qui partageraient
 * le leur reliraient un conteneur compilé pour l'autre, et les contrôles ne se joueraient pas.
 */
final class AuthorizationTestKernel extends Kernel
{
    /** @param list<class-string> $services */
    public function __construct(private readonly array $services = [])
    {
        // Hors debug : ce mode installe des gestionnaires d'erreurs globaux qu'un processus de
        // test ne doit pas hériter d'un cas au suivant. La compilation du conteneur, seule
        // chose examinée ici, est la même dans les deux modes.
        parent::__construct('test', false);
    }

    /**
     * Symfony 8.1 a déplacé l'interface des bundles dans le composant d'injection ;
     * `HttpKernel\Kernel` annonce encore l'ancienne, dépréciée. Le type est redit ici pour que
     * l'analyse statique juge sur celle que ces bundles implémentent réellement.
     *
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        // Porte les services de base — dont `event_dispatcher` — et se charge en premier :
        // FrameworkBundle s'appuie dessus.
        yield new ServicesBundle();
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new AuthorizationBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            // `php_errors.log` reste à sa valeur par défaut : l'activer ferait poser à
            // Symfony un gestionnaire d'erreurs global, que le processus de test garderait
            // d'un cas au suivant.
            $container->loadFromExtension('framework', [
                'secret' => 'authorization-bundle',
                'test' => true,
            ]);

            // Un pare-feu ouvert suffit : ce qui est éprouvé ici, c'est le câblage de
            // l'adaptateur, pas la décision — que rend un voter de l'application.
            $container->loadFromExtension('security', [
                'providers' => ['in_memory' => ['memory' => null]],
                'firewalls' => ['main' => ['security' => false]],
            ]);

            foreach ($this->services as $class) {
                $container->register($class, $class)
                    ->setAutoconfigured(true)
                    ->setAutowired(true)
                    ->setPublic(true);
            }
        });
    }

    /**
     * Hors du dépôt : le démarrage écrit un fichier de référence de configuration dans le
     * `config/` de la racine du projet, et ce fichier ne concerne que les applications.
     */
    public function getProjectDir(): string
    {
        return $this->getCacheDir();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ExposeForTestingPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/authorization-bundle/'.substr(md5(implode('|', $this->services)), 0, 12);
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }
}
