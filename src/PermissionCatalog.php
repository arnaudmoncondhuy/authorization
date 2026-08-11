<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * Les droits que le code exige, tous, et une seule fois chacun.
 *
 * C'est ce qui alimente l'écran où on les accorde. Il vient de la même source que le contrôle
 * lui-même — les déclarations portées par les cas d'usage — donc un droit ajouté au code ne
 * peut pas manquer à la liste, et une liste ne peut pas proposer un droit que rien n'exige.
 *
 * Il dit ce que le code réclame, pas ce que l'application déclare : une énumération peut
 * porter un cas qu'aucun cas d'usage n'exige, et ce cas n'apparaîtra pas ici. Comparer les
 * deux révèle une case qui n'accorderait rien.
 *
 * L'ordre est celui des identités, pour qu'une liste affichée deux fois se ressemble.
 */
final readonly class PermissionCatalog
{
    /** @var array<string, Permission> */
    private array $byId;

    /** @param iterable<Permission> $permissions */
    public function __construct(iterable $permissions = [])
    {
        $byId = [];

        foreach ($permissions as $permission) {
            $byId[$permission->id()] = $permission;
        }

        ksort($byId);

        $this->byId = $byId;
    }

    /** @return list<Permission> */
    public function all(): array
    {
        return array_values($this->byId);
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->byId);
    }

    /**
     * Répond sur une identité venue d'ailleurs — une ligne de droits accordés, par exemple.
     * Un droit stocké que plus aucun cas d'usage n'exige est une case devenue sans effet.
     */
    public function isRequired(string $id): bool
    {
        return isset($this->byId[$id]);
    }
}
