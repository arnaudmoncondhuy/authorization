<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Bridge\VoterSketch;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\StockPermission;
use PHPUnit\Framework\TestCase;

/**
 * Ce qu'un droit sans juge coûte à réparer : le voter qui manque est écrit, il ne reste qu'à
 * décider qui l'obtient.
 */
final class VoterSketchTest extends TestCase
{
    /**
     * La garde en fait partie — c'est elle qu'on oublie, et son oubli rend une erreur serveur
     * là où on attendait un refus.
     */
    public function testItWritesTheVoterThatIsMissing(): void
    {
        $sketch = $this->sketch(InvoicePermission::View);

        self::assertStringContainsString('final class InvoiceVoter extends Voter', $sketch);
        self::assertStringContainsString("'fixture.invoice.view',", $sketch);
        self::assertStringContainsString('!$token->getUser() instanceof UserInterface', $sketch);
    }

    /**
     * Et il n'accorde rien. Un squelette qui accorderait ouvrirait le droit à la seconde où il
     * est collé, c'est-à-dire avant que quiconque ait décidé qui le détient.
     */
    public function testItGrantsNothing(): void
    {
        $sketch = $this->sketch(InvoicePermission::View);

        self::assertStringNotContainsString('return true;', $sketch);
    }

    /**
     * Un squelette par contexte métier, et non un voter unique pour tout ce qui manque : c'est
     * le découpage que le paquet demande aux énumérations, et deux contextes réunis dans un
     * même `supports()` feraient d'un droit de facturation l'affaire du voter des stocks.
     */
    public function testItWritesOneVoterPerContext(): void
    {
        $sketches = (new VoterSketch())->of([InvoicePermission::View, StockPermission::Adjust]);

        self::assertSame(['src/Security/InvoiceVoter.php', 'src/Security/StockVoter.php'], array_keys($sketches));
        self::assertStringContainsString('final class InvoiceVoter extends Voter', $sketches['src/Security/InvoiceVoter.php']);
        self::assertStringContainsString('final class StockVoter extends Voter', $sketches['src/Security/StockVoter.php']);
    }

    /** Un contexte dont plusieurs droits manquent de juge les porte tous dans un seul voter. */
    public function testOneContextGathersItsOrphansInASingleVoter(): void
    {
        $sketch = $this->sketch(InvoicePermission::Finalize, InvoicePermission::Backdate);

        self::assertStringContainsString("'fixture.invoice.finalize',", $sketch);
        self::assertStringContainsString("'fixture.invoice.backdate',", $sketch);
    }

    /**
     * Une installation qui se tient n'a rien à proposer. Le squelette est le remède d'une
     * faute, pas un ornement du rapport.
     */
    public function testItWritesNothingWhenNothingIsMissing(): void
    {
        self::assertSame([], (new VoterSketch())->of([]));
    }

    private function sketch(InvoicePermission ...$unjudged): string
    {
        $sketches = (new VoterSketch())->of(array_values($unjudged));

        self::assertArrayHasKey('src/Security/InvoiceVoter.php', $sketches);

        return $sketches['src/Security/InvoiceVoter.php'];
    }
}
