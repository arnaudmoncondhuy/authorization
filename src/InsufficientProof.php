<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * L'appelant courant a le droit, mais n'a pas assez prouvé qui il est.
 *
 * Distincte de {@see MissingPermission}, et ce n'est pas une nuance : celle-là se répare en
 * accordant un droit, celle-ci en présentant un moyen. Les confondre ferait envoyer l'un chez
 * son administrateur quand il n'avait qu'à ressortir son téléphone.
 *
 * Le droit est vérifié en premier, et ce refus n'arrive donc jamais à qui n'y avait pas droit :
 * on ne fait pas prouver son identité à quelqu'un pour lui refuser ensuite l'action, et le
 * refus ne révèle pas l'existence d'un acte à qui ne pouvait pas le poser.
 *
 * Comme {@see MissingPermission}, c'est une exception du métier : un cas d'usage se joue aussi
 * hors requête. Sa traduction appartient à la surface, et sur le web elle n'est pas un refus
 * mais un détour — la page qui redemande, puis le retour. Ce paquet ne la traduit pas
 * lui-même : il ne connaît aucun écran, et n'a aucune adresse où renvoyer.
 */
final class InsufficientProof extends \RuntimeException
{
    private function __construct(
        public readonly Permission $permission,
        public readonly Proof $required,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function of(Permission $permission, Proof $required): self
    {
        return new self($permission, $required, \sprintf(
            'Autorisation « %s » accordée, mais l\'identité n\'est pas prouvée au niveau « %s ».',
            $permission->id(),
            $required->value,
        ));
    }
}
