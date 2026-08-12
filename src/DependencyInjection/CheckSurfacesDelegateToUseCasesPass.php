<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\DependencyInjection;

use ArnaudMoncondhuy\Authorization\CallsNoUseCase;
use ArnaudMoncondhuy\Authorization\UseCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Refuse de compiler le conteneur si une porte d'entrée n'appelle aucun verbe métier.
 *
 * Les trois autres contrôles interdisent à une surface de **déclarer** un droit. Aucun ne
 * l'oblige à en **traverser** un : un contrôleur qui injecte un dépôt et écrit directement ne
 * déclare rien, ne réclame rien, et passe entre les mailles. C'est par là qu'un inconnu sans
 * compte peut agir — la faute a été trouvée en vrai sur une application de ce paquet.
 *
 * **Les surfaces ne se déclarent pas : Symfony les marque déjà.** Un contrôleur porte
 * `controller.service_arguments`, une commande `console.command`, un consommateur de message
 * `messenger.message_handler`. Une porte ajoutée demain est donc examinée sans que personne
 * ait à tenir une liste — et une liste qu'on oublie de tenir laisse justement passer ce que ce
 * contrôle cherche.
 *
 * Une application qui ouvre une porte d'un autre genre — un outil pour un assistant, un point
 * d'entrée JSON-RPC — la fait examiner en ajoutant son propre tag à
 * {@see self::surfaceTagsOf()} via le paramètre `authorization.surface_tags`.
 *
 * La dispense se pose sur la classe, avec sa raison : {@see CallsNoUseCase}.
 */
final readonly class CheckSurfacesDelegateToUseCasesPass implements CompilerPassInterface
{
    /**
     * Les marques que le framework pose de lui-même sur ses portes d'entrée.
     *
     * @var list<string>
     */
    private const array FRAMEWORK_TAGS = [
        'controller.service_arguments',
        'console.command',
        'messenger.message_handler',
    ];

    public const string EXTRA_TAGS_PARAMETER = 'authorization.surface_tags';

    public function process(ContainerBuilder $container): void
    {
        $useCases = $this->useCaseClasses($container);
        $undelegated = [];

        foreach ($this->surfaceTagsOf($container) as $tag) {
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $service) {
                $class = $container->findDefinition($service)->getClass() ?? $service;
                $reflection = $container->getReflectionClass($class, false);

                if (!$reflection instanceof \ReflectionClass) {
                    continue;
                }

                if (self::comesFromADependency($reflection)) {
                    continue;
                }

                if ([] !== $reflection->getAttributes(CallsNoUseCase::class)) {
                    continue;
                }

                if (!self::reachesAUseCase($reflection, $useCases)) {
                    $undelegated[] = $reflection->getName();
                }
            }
        }

        // Une même classe peut porter plusieurs tags ; la faute, elle, est une.
        $undelegated = array_values(array_unique($undelegated));

        if ([] !== $undelegated) {
            throw new LogicException(\sprintf("Ces portes d'entrée n'appellent aucun cas d'usage, et ne traversent donc aucun contrôle de droit :\n  - %s\nFaire passer le geste par un cas d'usage, ou poser #[CallsNoUseCase('…')] en disant pourquoi.", implode("\n  - ", $undelegated)));
        }
    }

    /**
     * Les tags examinés : ceux du framework, plus ceux que l'application déclare pour ses
     * portes à elle.
     *
     * @return list<string>
     */
    private function surfaceTagsOf(ContainerBuilder $container): array
    {
        if (!$container->hasParameter(self::EXTRA_TAGS_PARAMETER)) {
            return self::FRAMEWORK_TAGS;
        }

        /** @var list<string> $extra */
        $extra = (array) $container->getParameter(self::EXTRA_TAGS_PARAMETER);

        return array_values(array_unique([...self::FRAMEWORK_TAGS, ...$extra]));
    }

    /**
     * Une porte livrée par une dépendance n'est pas la vôtre à gouverner.
     *
     * Le framework porte lui-même des dizaines de commandes, et un bundle tiers peut porter des
     * contrôleurs : leur demander d'appeler vos verbes métier n'aurait aucun sens.
     *
     * Le critère est le chemin du fichier, et c'est volontaire : tout autre demanderait une
     * configuration, et une configuration qu'on oublie de tenir laisse passer exactement ce que
     * ce contrôle cherche. Sa limite est écrite plutôt que tue — un projet qui aurait renommé
     * son dossier de dépendances y échapperait.
     *
     * @param \ReflectionClass<object> $reflection
     */
    private static function comesFromADependency(\ReflectionClass $reflection): bool
    {
        $file = $reflection->getFileName();

        return false === $file || str_contains(strtr($file, '\\', '/'), '/vendor/');
    }

    /**
     * Un verbe atteint par le constructeur, ou par un argument de méthode — Symfony injecte
     * les deux dans un contrôleur.
     *
     * @param \ReflectionClass<object> $reflection
     * @param list<class-string>       $useCases
     */
    private static function reachesAUseCase(\ReflectionClass $reflection, array $useCases): bool
    {
        $parameters = $reflection->getConstructor()?->getParameters() ?? [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $parameters = [...$parameters, ...$method->getParameters()];
        }

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $named = $type->getName();

            if (is_a($named, UseCase::class, true)) {
                return true;
            }

            // Un cas d'usage reçu par une classe parente ou une interface qu'il implémente.
            foreach ($useCases as $useCase) {
                if (is_a($useCase, $named, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<class-string> */
    private function useCaseClasses(ContainerBuilder $container): array
    {
        $classes = [];

        foreach (array_keys($container->findTaggedServiceIds(Tag::USE_CASE)) as $service) {
            $class = $container->findDefinition($service)->getClass() ?? $service;
            $reflection = $container->getReflectionClass($class, false);

            if ($reflection instanceof \ReflectionClass && $reflection->implementsInterface(UseCase::class)) {
                $classes[] = $reflection->getName();
            }
        }

        return array_values(array_unique($classes));
    }
}
