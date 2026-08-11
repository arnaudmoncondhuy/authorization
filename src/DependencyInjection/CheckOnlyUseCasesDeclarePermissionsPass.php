<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\DependencyInjection;

use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\UseCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Refuse de compiler le conteneur si un droit est déclaré ailleurs que sur un cas d'usage.
 *
 * Un droit exigé par un verbe métier ne s'écrit qu'à un seul endroit. Une page, une API, un
 * outil ou une console qui le redéclarerait de son côté ouvrirait la porte à ce que les deux
 * copies s'écartent : durcir l'une sans l'autre laisse une porte dérobée, et rien ne le dit.
 *
 * La règle vise ce qui déclare, non ce qui appelle : elle n'a donc aucune liste de surfaces à
 * tenir à jour, et vaut pour toute classe du conteneur, surface ou non.
 */
final readonly class CheckOnlyUseCasesDeclarePermissionsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $usurpers = [];

        foreach ($container->getDefinitions() as $service => $definition) {
            $class = $definition->getClass() ?? $service;
            $reflection = $container->getReflectionClass($class, false);

            if (!$reflection instanceof \ReflectionClass) {
                continue;
            }

            if ([] === $reflection->getAttributes(RequiresPermission::class)) {
                continue;
            }

            if (!$reflection->implementsInterface(UseCase::class)) {
                $usurpers[] = $reflection->getName();
            }
        }

        // Une même classe peut porter plusieurs définitions de service ; la faute, elle, est une.
        $usurpers = array_values(array_unique($usurpers));

        if ([] !== $usurpers) {
            throw new LogicException(\sprintf("Ces classes déclarent un droit sans être des cas d'usage :\n  - %s", implode("\n  - ", $usurpers)));
        }
    }
}
