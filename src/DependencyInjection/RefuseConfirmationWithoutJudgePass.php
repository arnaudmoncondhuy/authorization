<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\DependencyInjection;

use ArnaudMoncondhuy\Authorization\Confirmation;
use ArnaudMoncondhuy\Authorization\ConfirmationOfIntent;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Refuse une exigence de confirmation que personne ne saurait juger.
 *
 * Ce paquet nomme les niveaux de {@see Confirmation} et n'en juge aucun : c'est
 * {@see ConfirmationOfIntent} qui répond, et il vient d'ailleurs. Un droit déclaré
 * `confirmation: Confirmation::Typed` dans une application où rien n'implémente ce contrat
 * laisse une question sans réponse, et il n'y a que deux façons de la traiter à l'exécution —
 * laisser passer, ou tout refuser. La première est une faille silencieuse, la seconde une panne
 * silencieuse. On arrête donc la compilation.
 *
 * C'est aussi ce qui tient l'installation dans le temps. Le jour où quelqu'un retire de
 * l'application le paquet qui juge, elle ne démarre plus : elle n'ouvre pas d'elle-même les
 * actes qu'elle protégeait la veille.
 *
 * Elle ne dit rien à qui ne déclare aucune exigence : le paquet s'installe et se comporte
 * exactement comme avant l'existence de cet axe.
 *
 * Elle vit dans `build()` et non dans l'extension parce que le juge est déclaré par l'extension
 * d'un autre paquet, et que l'ordre de chargement des extensions ne se commande pas.
 */
final readonly class RefuseConfirmationWithoutJudgePass implements CompilerPassInterface
{
    /**
     * Ce qui juge une confirmation d'intention, ou nul quand rien ne le fait. Ce que lisent
     * `authorization:doctor` et le panneau de la barre de debug : le nom plutôt que le service,
     * parce que l'injecter suffirait à le compter utilisé dans une application qui n'exige
     * aucune confirmation.
     */
    public const string JUDGE_PARAMETER = 'authorization.confirmation_judge';

    public function process(ContainerBuilder $container): void
    {
        // L'alias autant que la définition : le contrat est ce qu'on injecte, et l'application
        // comme le paquet qui juge sont libres de le brancher de l'une ou l'autre façon.
        $judge = $container->has(ConfirmationOfIntent::class) ? self::nameOf($container) : null;

        $container->setParameter(self::JUDGE_PARAMETER, $judge);

        /** @var array<string, string> $required */
        $required = $container->hasParameter(RegisterPermissionCatalogPass::REQUIRED_CONFIRMATIONS_PARAMETER)
            ? (array) $container->getParameter(RegisterPermissionCatalogPass::REQUIRED_CONFIRMATIONS_PARAMETER)
            : [];

        if ([] === $required || null !== $judge) {
            return;
        }

        $listing = array_map(
            static fn (string $id, string $confirmation): string => \sprintf('  - %s (%s)', $id, $confirmation),
            array_keys($required),
            array_values($required),
        );

        $fault = \sprintf(
            "Ces droits exigent une confirmation d'intention, et rien n'implémente %s pour la juger :",
            ConfirmationOfIntent::class,
        );

        $remedy = \sprintf(
            'Installer un paquet qui fournit ce contrat — arnaudmoncondhuy/authentication-policy le fait '
            .'dès que la confirmation est allumée — ou retirer l\'argument `confirmation:` de %s.',
            RequiresPermission::class,
        );

        throw new LogicException(implode("\n", [$fault, ...$listing, $remedy]));
    }

    /**
     * Le nom de ce qui juge, en remontant l'alias jusqu'à la définition qui le remplit.
     *
     * Le service lui-même n'est jamais lu ici : ce qu'on veut est un nom à afficher, et il
     * n'existe pas encore d'instance à cet instant de la compilation.
     */
    private static function nameOf(ContainerBuilder $container): string
    {
        $id = ConfirmationOfIntent::class;

        while ($container->hasAlias($id)) {
            $id = (string) $container->getAlias($id);
        }

        return $container->hasDefinition($id)
            ? ($container->getDefinition($id)->getClass() ?? $id)
            : $id;
    }
}
