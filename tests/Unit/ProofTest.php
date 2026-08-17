<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Proof;
use PHPUnit\Framework\TestCase;

/**
 * L'échelle, et le seul sens dans lequel elle se parcourt.
 *
 * Ce qui est éprouvé ici n'est pas un calcul mais une règle : un niveau ne peut que resserrer.
 * Elle tient la résolution du catalogue — deux cas d'usage qui portent le même droit — et la
 * réponse d'un juge, sans qu'aucun des deux ait à la réécrire.
 */
final class ProofTest extends TestCase
{
    public function testTheScaleGoesUpAndOnlyUp(): void
    {
        self::assertSame(0, Proof::None->rank());
        self::assertSame(1, Proof::Strong->rank());
        self::assertSame(2, Proof::Recent->rank());
    }

    /** Ce qu'on a prouvé couvre ce qu'on demande dès qu'il est au moins aussi haut. */
    public function testAProvenLevelSatisfiesEveryLevelBelowIt(): void
    {
        self::assertTrue(Proof::Recent->satisfies(Proof::Strong));
        self::assertTrue(Proof::Recent->satisfies(Proof::Recent));
        self::assertTrue(Proof::Strong->satisfies(Proof::None));
    }

    public function testAProvenLevelDoesNotSatisfyWhatIsAboveIt(): void
    {
        self::assertFalse(Proof::Strong->satisfies(Proof::Recent));
        self::assertFalse(Proof::None->satisfies(Proof::Strong));
    }

    /** N'exigeant rien, ce niveau est satisfait par tout, y compris par rien. */
    public function testNoneIsSatisfiedByAnything(): void
    {
        self::assertTrue(Proof::None->satisfies(Proof::None));
        self::assertTrue(Proof::Recent->satisfies(Proof::None));
    }

    public function testTheStrongestOfTwoWins(): void
    {
        self::assertSame(Proof::Recent, Proof::strongest(Proof::Strong, Proof::Recent));
        self::assertSame(Proof::Recent, Proof::strongest(Proof::Recent, Proof::Strong));
        self::assertSame(Proof::Strong, Proof::strongest(Proof::None, Proof::Strong));
        self::assertSame(Proof::None, Proof::strongest(Proof::None, Proof::None));
    }
}
