<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;

/**
 * Un trait qui déclare un droit, où l'attribut n'est lu par personne.
 *
 * Le geste paraît naturel — mutualiser une garde entre plusieurs cas d'usage — et il est
 * inerte. `\Attribute::TARGET_CLASS` accepte un trait, donc rien ne proteste à l'écriture ;
 * mais `ReflectionClass::getAttributes()` appelé sur la classe qui emploie le trait ne rend
 * pas les attributs du trait. Les passes de compilation n'ont donc rien à recenser, le droit
 * n'entre dans aucun inventaire, et le verbe se ferme pour tout le monde.
 *
 * Le corps, lui, n'est pas relu ici : ses méthodes sont déjà lues à travers la classe qui les
 * emploie, où `getDeclaringClass()` les rattache.
 */
#[RequiresPermission(InvoicePermission::Create)]
trait DeclaringTrait
{
    public function create(): void
    {
    }
}
