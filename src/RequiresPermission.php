<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * Déclare un droit qu'un {@see UseCase} exige.
 *
 * Répétable : un cas d'usage qui porte plusieurs verbes les déclare tous. Modifier une fiche
 * dont certains champs sont sensibles réclame le droit de base, puis le droit fin au moment
 * précis où le champ change — les deux se déclarent ici, et `__invoke()` réclame celui qui
 * correspond à ce qu'on lui demande.
 *
 * L'attribut déclare ; il n'applique pas. C'est {@see Authorizer::require()} qui refuse, et
 * une déclaration que le corps ne réclame jamais ferait apparaître un droit à accorder que
 * rien n'appliquerait.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class RequiresPermission
{
    public function __construct(public Permission $permission)
    {
    }
}
