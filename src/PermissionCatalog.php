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

    /** @var array<string, Proof> */
    private array $proofById;

    /**
     * @param iterable<Permission> $permissions
     * @param array<string, Proof> $proofs      le niveau de preuve exigé, par identité de
     *                                          droit. Vient de la même déclaration que le
     *                                          droit lui-même, donc il ne peut pas la
     *                                          contredire ; les identités absentes n'exigent
     *                                          rien de plus que le droit
     */
    public function __construct(iterable $permissions = [], array $proofs = [])
    {
        $byId = [];

        foreach ($permissions as $permission) {
            $byId[$permission->id()] = $permission;
        }

        ksort($byId);

        $this->byId = $byId;
        $this->proofById = $proofs;
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

    /**
     * Le niveau de preuve qu'un droit exige, en plus d'être détenu.
     *
     * Rend {@see Proof::None} pour une identité qu'aucune déclaration ne relève, y compris
     * inconnue : un droit qui n'exige rien de plus et un droit qui n'existe pas se traitent
     * ici de la même façon, et c'est la détention du droit — vérifiée avant — qui sépare les
     * deux cas.
     */
    public function proofFor(string $id): Proof
    {
        return $this->proofById[$id] ?? Proof::None;
    }

    /**
     * Les droits qui exigent plus que d'être détenus, avec leur niveau.
     *
     * Ce que lisent `authorization:doctor` et le panneau de la barre de debug : une exigence
     * de preuve ne se voit nulle part ailleurs sans ouvrir le code des cas d'usage.
     *
     * @return array<string, Proof>
     */
    public function proofs(): array
    {
        $proofs = array_filter($this->proofById, static fn (Proof $proof): bool => Proof::None !== $proof);

        ksort($proofs);

        return $proofs;
    }
}
