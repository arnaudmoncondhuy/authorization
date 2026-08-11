<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * L'une de ses deux gardes est en commentaire — le geste le plus banal d'un débogage, et le
 * plus dangereux.
 *
 * Le geste sensible s'exécute alors sans arbitrage, tandis que le texte cherché figure
 * toujours dans le fichier en toutes lettres. Un contrôle qui lirait le corps comme du texte
 * brut certifierait donc que le droit est réclamé pendant que personne ne le réclame : il
 * ouvrirait au lieu de fermer, ce qui est la pire faute qu'il puisse commettre.
 *
 * La seconde garde reste en place, et c'est ce qui rend le cas réaliste : l'analyse statique
 * ne peut plus signaler un contrat injecté sans emploi.
 */
#[RequiresPermission(InvoicePermission::View)]
#[RequiresPermission(InvoicePermission::Create)]
final readonly class CommentedGuardUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(): void
    {
        $this->access->require(InvoicePermission::View);

        // $this->access->require(InvoicePermission::Create);
    }
}
