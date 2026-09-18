<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * L'appelant courant a le droit, et n'a pas confirmé qu'il voulait s'en servir.
 *
 * Distincte de {@see MissingPermission} et de {@see InsufficientProof}, et ce n'est pas une
 * nuance : la première se répare en accordant un droit, la deuxième en présentant un moyen,
 * celle-ci en recopiant ce que l'écran demande. Les confondre enverrait quelqu'un chez son
 * administrateur, ou chercher son téléphone, quand il n'avait qu'à relire ce qu'il s'apprêtait
 * à faire.
 *
 * Le droit est vérifié en premier, et ce refus n'arrive donc jamais à qui n'y avait pas droit.
 *
 * Comme les deux autres, c'est une exception du métier : un cas d'usage se joue aussi hors
 * requête, et une confirmation qui n'a pas été recueillie est alors la réponse juste — rien
 * n'a confirmé, donc rien ne part. Sa traduction appartient à la surface : c'est elle qui sait
 * réafficher l'écran avec le champ à remplir.
 */
final class MissingConfirmation extends \RuntimeException
{
    private function __construct(
        public readonly Permission $permission,
        public readonly Confirmation $required,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function of(Permission $permission, Confirmation $required): self
    {
        return new self($permission, $required, \sprintf(
            'Autorisation « %s » accordée, mais l\'intention n\'est pas confirmée au niveau « %s ».',
            $permission->id(),
            $required->value,
        ));
    }
}
