<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Bridge;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\InsufficientProof;
use ArnaudMoncondhuy\Authorization\MissingPermission;
use ArnaudMoncondhuy\Authorization\Permission;
use ArnaudMoncondhuy\Authorization\PermissionCatalog;
use ArnaudMoncondhuy\Authorization\Proof;
use ArnaudMoncondhuy\Authorization\ProofOfIdentity;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Fait décider les droits par le contrôle d'accès de l'application.
 *
 * L'identité du droit devient l'attribut qu'on lui soumet : tout le modèle de l'application —
 * ce qu'un rôle accorde, ce qu'un ensemble contient, ce qu'un compte s'est vu attribuer — vit
 * dans les voters, et rien de tout cela ne remonte jusqu'aux cas d'usage.
 *
 * Ce paquet ne fournit aucun voter : ce qui décide appartient à l'application.
 *
 * Aucun cycle à craindre : un voter décide, il n'a jamais besoin de ce service pour le faire.
 */
final readonly class SecurityAuthorizer implements Authorizer
{
    public function __construct(
        private AuthorizationCheckerInterface $accessChecker,
        private PermissionCatalog $catalog,
        /**
         * Nul quand aucun paquet ne sait juger une preuve d'identité, ce qui est le cas de
         * toute application qui n'en exige aucune. Celle qui en exige une sans juge n'atteint
         * pas ce point : {@see \ArnaudMoncondhuy\Authorization\DependencyInjection\RefuseProofWithoutJudgePass}
         * arrête sa compilation.
         */
        private ?ProofOfIdentity $identity = null,
    ) {
    }

    /**
     * Ne répond que sur le droit, jamais sur la preuve.
     *
     * C'est ce qui fait afficher un bouton qu'on n'a pas encore le droit de presser, et c'est
     * voulu : masquer l'action à qui la détient parce qu'il n'a pas prouvé son identité assez
     * récemment lui ferait chercher un droit qu'il possède. Le détour se demande au clic, où
     * il s'explique — c'est {@see self::require()} qui l'oppose.
     */
    public function can(Permission $permission): bool
    {
        return $this->accessChecker->isGranted($permission->id());
    }

    public function require(Permission $permission): void
    {
        // Le droit d'abord : on ne fait pas ressortir son téléphone à quelqu'un pour lui
        // refuser l'action ensuite, et le détour ne révèle pas l'existence d'un acte à qui ne
        // pouvait de toute façon pas le poser.
        if (!$this->can($permission)) {
            throw MissingPermission::of($permission);
        }

        $required = $this->catalog->proofFor($permission->id());

        if (Proof::None === $required) {
            return;
        }

        // Le nul refuse au lieu de laisser passer. Il ne devrait jamais se présenter — la
        // compilation s'arrête avant — mais c'est le sens dans lequel une garantie doit tomber
        // si jamais elle tombe.
        if (null === $this->identity || !$this->identity->meets($required)) {
            throw InsufficientProof::of($permission, $required);
        }
    }
}
