<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Web;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;

/**
 * Réclame un droit sans être un cas d'usage — la faute qu'aucun contrôle de compilation ne
 * peut voir, parce que les trois ne jugent que ce qui implémente le marqueur.
 *
 * Elle s'écrit sans mauvaise intention : on porte le contrôle dans la surface plutôt que dans
 * le verbe, ou l'on oublie simplement l'interface. Le droit n'entre alors dans aucun
 * inventaire, personne ne peut l'accorder, et le geste reste gouverné par rien.
 */
final readonly class RequiringController
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(): string
    {
        $this->access->require(InvoicePermission::View);

        return 'invoice';
    }
}
