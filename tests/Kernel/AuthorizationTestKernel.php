<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Kernel;

use ArnaudMoncondhuy\Authorization\AuthorizationBundle;
use Composer\InstalledVersions;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
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
    /**
     * @param list<class-string>   $services
     * @param array<string, mixed> $providers    les fournisseurs de comptes de l'application. Un seul
     *                                           par défaut, mais le nombre compte : SecurityExtension
     *                                           ne pose l'alias `UserProviderInterface` que s'il y en a
     *                                           exactement un, et ne pose rien du tout s'il n'y en a
     *                                           aucun
     * @param bool                 $security     faux pour l'application « sans pare-feu » que le paquet
     *                                           dit servir : SecurityBundle n'est alors pas enregistré
     *                                           du tout, ce qui n'est pas la même chose qu'un pare-feu
     *                                           ouvert
     * @param ?string              $userProvider le fournisseur de comptes que l'application nomme
     *                                           sous `authorization.user_provider`. Nul pour ne rien
     *                                           configurer du tout, ce qui est le cas courant
     * @param bool                 $profiler     vrai pour l'application de développement : WebProfilerBundle
     *                                           enregistré, et le profileur qui collecte. C'est à ce
     *                                           bundle, et non au mode debug, que le paquet conditionne
     *                                           son panneau
     */
    public function __construct(
        private readonly array $services = [],
        private readonly array $providers = ['in_memory' => ['memory' => null]],
        private readonly bool $security = true,
        private readonly ?string $userProvider = null,
        private readonly bool $profiler = false,
    ) {
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
        //
        // Il n'existe qu'à partir de 8.1. En 7.4, FrameworkBundle porte encore ces services
        // lui-même : le noyau se monte sans lui, et c'est ce qui permet d'éprouver le paquet
        // sur les deux branches qu'il déclare soutenir.
        if (class_exists(ServicesBundle::class)) {
            yield new ServicesBundle();
        }

        yield new FrameworkBundle();

        if ($this->security) {
            yield new SecurityBundle();
        }

        // Les deux ensemble : WebProfilerBundle ne se monte pas sans Twig, dont il tire ses
        // gabarits — et le panneau du paquet est l'un d'eux.
        if ($this->profiler) {
            yield new TwigBundle();
            yield new WebProfilerBundle();
        }

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
                // Posée explicitement parce que la 7.3 déprécie de ne pas le faire : sa valeur
                // par défaut change en 8.0, et `failOnDeprecation` fait de cet avertissement un
                // échec. C'est la valeur de la 8.x qui est reprise, pour que les deux branches
                // soient éprouvées sur le même réglage.
                'property_info' => ['with_constructor_extractor' => true],
            ]);

            // Un pare-feu ouvert suffit : ce qui est éprouvé ici, c'est le câblage de
            // l'adaptateur, pas la décision — que rend un voter de l'application.
            //
            // `providers` n'est posé que s'il y en a : la clé vide et la clé absente ne sont
            // pas la même configuration pour SecurityExtension.
            if ($this->security) {
                $security = ['firewalls' => ['main' => ['security' => false]]];

                if ([] !== $this->providers) {
                    $security['providers'] = $this->providers;
                }

                $container->loadFromExtension('security', $security);
            }

            // Le profileur doit collecter pour que le tag `data_collector` soit lu : désactivé,
            // la passe qui l'honore ne tourne pas, et le collecteur du paquet serait retiré du
            // conteneur comme un service que personne n'utilise.
            //
            // La barre elle-même est éteinte : elle exige un routeur pour bâtir le lien vers le
            // profileur, et ce qui est éprouvé ici est le câblage, pas l'affichage.
            if ($this->profiler) {
                $profiler = ['enabled' => true, 'collect' => true];

                // Les deux branches se contredisent sur cette clé : la 7.3 déprécie de ne pas
                // la poser, la 8.1 déprécie de la poser. Aucune configuration ne convient aux
                // deux, et `failOnDeprecation` fait de l'un comme de l'autre un échec — elle
                // se pose donc sur la seule branche qui la réclame.
                //
                // C'est FrameworkBundle qu'on interroge, et non la constante de version du
                // noyau : la clé est la sienne, la dépréciation aussi, et c'est donc lui qui
                // dit laquelle des deux règles s'applique.
                $framework = InstalledVersions::getVersion('symfony/framework-bundle');

                if (null !== $framework && version_compare($framework, '8.1', '<')) {
                    $profiler['collect_serializer_data'] = true;
                }

                $container->loadFromExtension('framework', [
                    'profiler' => $profiler,
                    // WebProfilerBundle en tire le lien vers le profileur, et le réclame donc
                    // même sur une application qui ne sert aucune page. La ressource est vide,
                    // et c'est ce qu'il faut dire : ce qui est éprouvé ici est le câblage.
                    'router' => ['resource' => __DIR__.'/routing.php', 'type' => 'php', 'utf8' => true],
                ]);
                $container->loadFromExtension('twig', []);
                $container->loadFromExtension('web_profiler', ['toolbar' => false]);
            }

            // Rien n'est posé quand l'application ne nomme pas son annuaire : la clé absente
            // et la clé nulle ne sont pas la même configuration à écrire dans un projet, et
            // c'est la première qui est le cas courant.
            if (null !== $this->userProvider) {
                $container->loadFromExtension('authorization', ['user_provider' => $this->userProvider]);
            }

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
        // La version de Symfony entre dans la clé. Sans elle, une même arborescence éprouvée
        // successivement sur deux branches relit le conteneur compilé pour l'autre, et la suite
        // tombe sur une classe qui n'existe pas dans celle qui tourne — un échec qui accuse le
        // paquet d'une faute qui appartient au cache.
        $key = md5(implode('|', [
            Kernel::VERSION,
            json_encode($this->providers),
            $this->security ? 'security' : 'bare',
            $this->profiler ? 'profiler' : 'sans profileur',
            $this->userProvider ?? 'toute la chaîne',
            ...$this->services,
        ]));

        return sys_get_temp_dir().'/authorization-bundle/'.substr($key, 0, 12);
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }
}
