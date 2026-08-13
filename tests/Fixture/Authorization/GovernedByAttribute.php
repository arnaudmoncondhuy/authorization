<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization;

use ArnaudMoncondhuy\Authorization\RequiresPermission;

/**
 * Une interface qui porte l'attribut, où il ne sert à rien.
 *
 * Elle n'existe que pour prouver que les interfaces sont désormais lues. `class_exists()` rend
 * **faux** pour une interface : tant que le balayage s'en tenait à lui, ce fichier — et tous
 * ceux de son espèce — étaient écartés sans un mot, et l'attribut posé ici n'était vu par
 * personne. Ni les passes, qui ne jugent que des services, ni ce contrôle, qui ne l'atteignait
 * pas.
 */
#[RequiresPermission(InvoicePermission::View)]
interface GovernedByAttribute
{
    public function __invoke(): void;
}
