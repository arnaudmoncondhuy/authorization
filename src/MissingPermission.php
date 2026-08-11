<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * L'appelant courant n'a pas un droit qu'un cas d'usage exige.
 *
 * Une exception du métier, et non celle du pare-feu : un cas d'usage se joue aussi hors HTTP,
 * et c'est à chaque surface de traduire ce refus dans son langage — un statut 403 pour une
 * page, une erreur nommée pour un outil, un message pour une console.
 *
 * Elle garde le droit refusé : une surface peut ainsi dire lequel manque, et celui à qui on
 * le rapporte sait quoi accorder.
 */
final class MissingPermission extends \RuntimeException
{
    private function __construct(public readonly Permission $permission, string $message)
    {
        parent::__construct($message);
    }

    public static function of(Permission $permission): self
    {
        return new self($permission, \sprintf('Autorisation « %s » requise.', $permission->id()));
    }
}
