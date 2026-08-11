<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\DependencyInjection;

use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\UseCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Refuse de compiler le conteneur si un cas d'usage ne déclare aucun droit.
 *
 * Un verbe sans droit déclaré n'apparaît dans aucun inventaire : personne ne peut le lui
 * accorder, et rien ne signale qu'il s'exécute sans arbitrage.
 *
 * Les cas d'usage sont trouvés par le tag que le bundle pose sur l'interface — pas par un
 * parcours de fichiers, qui dépendrait d'une convention de chemins et se périmerait.
 *
 * Toutes les fautes sont nommées d'un coup : les corriger une par une, à chaque compilation,
 * coûterait autant de tours que de fautes.
 */
final readonly class CheckUseCasesDeclarePermissionsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $silent = [];

        foreach (array_keys($container->findTaggedServiceIds(Tag::USE_CASE)) as $service) {
            $class = $container->findDefinition($service)->getClass() ?? $service;
            $reflection = $container->getReflectionClass($class, false);

            if (!$reflection instanceof \ReflectionClass || !$reflection->implementsInterface(UseCase::class)) {
                continue;
            }

            if ([] === $reflection->getAttributes(RequiresPermission::class)) {
                $silent[] = $reflection->getName();
            }
        }

        if ([] !== $silent) {
            throw new LogicException(\sprintf("Ces cas d'usage ne déclarent aucun droit, et resteraient donc inaccordables :\n  - %s", implode("\n  - ", $silent)));
        }
    }
}
