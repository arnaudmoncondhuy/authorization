<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

/**
 * La classe qui emploie {@see DeclaringTrait}, et qui n'hérite pas de sa déclaration.
 *
 * C'est ce que le fixture démontre : elle porte bien la méthode du trait, mais
 * `ReflectionClass::getAttributes()` appelée ici ne rend rien. Le droit déclaré sur le trait
 * n'entre donc dans aucun inventaire, et le verbe se ferme pour tout le monde sans qu'une
 * seule erreur soit levée nulle part — la forme de faute que ce paquet existe pour rendre
 * impossible.
 *
 * Elle n'implémente pas le marqueur : ce qui est éprouvé ici est la propagation de l'attribut,
 * pas l'accord entre une déclaration et un corps.
 */
final class EmploysTheDeclaringTrait
{
    use DeclaringTrait;
}
