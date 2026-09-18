<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Confirmation;
use ArnaudMoncondhuy\Authorization\Proof;
use PHPUnit\Framework\TestCase;

/**
 * Le second axe, et le seul sens dans lequel il se parcourt.
 *
 * Même règle que pour les preuves : un niveau ne peut que resserrer. Elle tient la résolution du
 * catalogue — deux cas d'usage qui portent le même droit — et la réponse de qui recueille la
 * confirmation, sans qu'aucun des deux ait à la réécrire.
 */
final class ConfirmationTest extends TestCase
{
    public function testTheScaleGoesUpAndOnlyUp(): void
    {
        self::assertSame(0, Confirmation::None->rank());
        self::assertSame(1, Confirmation::Typed->rank());
        self::assertSame(2, Confirmation::Secret->rank());
    }

    /** Ce qu'on a confirmé couvre ce qu'on demande dès qu'il est au moins aussi haut. */
    public function testAGivenLevelSatisfiesEveryLevelBelowIt(): void
    {
        self::assertTrue(Confirmation::Secret->satisfies(Confirmation::Typed));
        self::assertTrue(Confirmation::Secret->satisfies(Confirmation::Secret));
        self::assertTrue(Confirmation::Typed->satisfies(Confirmation::None));
    }

    public function testAGivenLevelDoesNotSatisfyWhatIsAboveIt(): void
    {
        self::assertFalse(Confirmation::Typed->satisfies(Confirmation::Secret));
        self::assertFalse(Confirmation::None->satisfies(Confirmation::Typed));
    }

    /** N'exigeant rien, ce niveau est satisfait par tout, y compris par rien. */
    public function testNoneIsSatisfiedByAnything(): void
    {
        self::assertTrue(Confirmation::None->satisfies(Confirmation::None));
        self::assertTrue(Confirmation::Secret->satisfies(Confirmation::None));
    }

    public function testTheStrongestOfTwoWins(): void
    {
        self::assertSame(Confirmation::Secret, Confirmation::strongest(Confirmation::Typed, Confirmation::Secret));
        self::assertSame(Confirmation::Secret, Confirmation::strongest(Confirmation::Secret, Confirmation::Typed));
        self::assertSame(Confirmation::Typed, Confirmation::strongest(Confirmation::None, Confirmation::Typed));
        self::assertSame(Confirmation::None, Confirmation::strongest(Confirmation::None, Confirmation::None));
    }

    /**
     * Les deux axes ne se rencontrent pas.
     *
     * Le cas qui compte, et la raison d'être de cette énumération séparée : recopier une phrase
     * ne vaut pas un second facteur, et présenter un second facteur ne dit pas qu'on voulait
     * poser l'acte. Un jour où les deux échelles se mêleraient, c'est ce qui casserait —
     * `satisfies()` n'accepte que sa propre sorte, et le typage le tient.
     */
    public function testTheTwoAxesShareNoValue(): void
    {
        $proofs = array_map(static fn (Proof $proof): string => $proof->value, Proof::cases());
        $confirmations = array_map(
            static fn (Confirmation $confirmation): string => $confirmation->value,
            Confirmation::cases(),
        );

        self::assertSame(['none'], array_values(array_intersect($proofs, $confirmations)));
    }
}
