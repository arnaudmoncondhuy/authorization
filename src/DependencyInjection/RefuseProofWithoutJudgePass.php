<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\DependencyInjection;

use ArnaudMoncondhuy\Authorization\Proof;
use ArnaudMoncondhuy\Authorization\ProofOfIdentity;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Refuse une exigence de preuve que personne ne saurait juger.
 *
 * Ce paquet nomme les niveaux de {@see Proof} et n'en juge aucun : c'est {@see ProofOfIdentity}
 * qui répond, et il vient d'ailleurs. Un droit déclaré `proof: Proof::Strong` dans une
 * application où rien n'implémente ce contrat laisse une question sans réponse, et il n'y a que
 * deux façons de la traiter à l'exécution — laisser passer, ou tout refuser. La première est
 * une faille silencieuse, la seconde une panne silencieuse. On arrête donc la compilation.
 *
 * C'est aussi ce qui tient l'installation dans le temps. Le jour où quelqu'un retire de
 * l'application le paquet qui juge, elle ne démarre plus : elle n'ouvre pas d'elle-même les
 * actes qu'elle protégeait la veille. C'est la seule garantie qui compte ici, et elle ne vaut
 * que parce qu'elle tombe à la compilation, y compris sur le poste de qui vient de faire le
 * retrait.
 *
 * Elle ne dit rien à qui ne déclare aucune exigence : le paquet s'installe et se comporte
 * exactement comme avant l'existence de cette échelle.
 *
 * Elle vit dans `build()` et non dans l'extension parce que le juge est déclaré par l'extension
 * d'un autre paquet, et que l'ordre de chargement des extensions ne se commande pas.
 */
final readonly class RefuseProofWithoutJudgePass implements CompilerPassInterface
{
    /**
     * Ce qui juge une preuve d'identité, ou nul quand rien ne le fait. Ce que lisent
     * `authorization:doctor` et le panneau de la barre de debug : le nom plutôt que le service,
     * parce que l'injecter suffirait à le compter utilisé dans une application qui n'exige
     * aucune preuve.
     */
    public const string JUDGE_PARAMETER = 'authorization.proof_judge';

    public function process(ContainerBuilder $container): void
    {
        // L'alias autant que la définition : le contrat est ce qu'on injecte, et l'application
        // comme le paquet qui juge sont libres de le brancher de l'une ou l'autre façon.
        $judge = $container->has(ProofOfIdentity::class) ? self::nameOf($container) : null;

        $container->setParameter(self::JUDGE_PARAMETER, $judge);

        /** @var array<string, string> $required */
        $required = $container->hasParameter(RegisterPermissionCatalogPass::REQUIRED_PROOFS_PARAMETER)
            ? (array) $container->getParameter(RegisterPermissionCatalogPass::REQUIRED_PROOFS_PARAMETER)
            : [];

        if ([] === $required || null !== $judge) {
            return;
        }

        $listing = array_map(
            static fn (string $id, string $proof): string => \sprintf('  - %s (%s)', $id, $proof),
            array_keys($required),
            array_values($required),
        );

        $fault = \sprintf(
            "Ces droits exigent une preuve d'identité, et rien n'implémente %s pour la juger :",
            ProofOfIdentity::class,
        );

        $remedy = \sprintf(
            'Installer un paquet qui fournit ce contrat — arnaudmoncondhuy/authentication-policy le fait '
            .'dès qu\'un mécanisme est allumé — ou retirer l\'argument `proof:` de %s.',
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
        $id = ProofOfIdentity::class;

        while ($container->hasAlias($id)) {
            $id = (string) $container->getAlias($id);
        }

        return $container->hasDefinition($id)
            ? ($container->getDefinition($id)->getClass() ?? $id)
            : $id;
    }
}
