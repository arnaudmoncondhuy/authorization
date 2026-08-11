<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Testing\PermissionUsage;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice\DeclaresWithoutDemandingUseCase;
use PHPUnit\Framework\TestCase;

/**
 * L'écart entre ce qu'un cas d'usage promet et ce qu'il fait.
 *
 * Deux vérifications de nature différente : le paquet lui-même ne doit contenir aucun écart,
 * et le détecteur doit savoir en trouver un. La seconde n'est pas un luxe — un détecteur qui
 * ne trouve jamais rien passe au vert exactement comme un code sans faute.
 */
final class PermissionUsageTest extends TestCase
{
    public function testTheContractItselfBreaksNothing(): void
    {
        self::assertSame([], PermissionUsage::violationsUnder(self::packageRoot(), 'ArnaudMoncondhuy\\Authorization\\'));
    }

    public function testItCatchesADeclarationThatIsNeverDemanded(): void
    {
        self::assertContains(
            'DeclaresWithoutDemandingUseCase déclare fixture.invoice.create sans jamais le réclamer',
            self::violationsInFixtures(),
        );
    }

    /**
     * Le droit que ce même cas d'usage réclame bel et bien n'est pas signalé : le détecteur
     * distingue les deux déclarations d'une même classe.
     */
    public function testItLeavesAloneWhatIsProperlyDemanded(): void
    {
        self::assertNotContains(
            'DeclaresWithoutDemandingUseCase déclare fixture.invoice.view sans jamais le réclamer',
            self::violationsInFixtures(),
        );
    }

    /**
     * L'ensemble exact des fautes que les fixtures contiennent. Une assertion sur l'ensemble
     * plutôt que sur chaque cas : elle échoue aussi bien quand une faute cesse d'être vue que
     * lorsqu'un cas d'usage conforme se met à être nommé.
     */
    public function testTheFixturesYieldExactlyTheExpectedFaults(): void
    {
        self::assertSame([
            'CommentedGuardUseCase déclare fixture.invoice.create sans jamais le réclamer',
            'ComputesItsPermissionUseCase réclame un droit par une valeur, que ce contrôle ne sait pas rapprocher de ses déclarations',
            'DeclaresWithoutDemandingUseCase déclare fixture.invoice.create sans jamais le réclamer',
            'DeclaringController déclare un droit sans être un cas d\'usage',
            'DemandsUndeclaredUseCase réclame InvoicePermission::Create sans l\'avoir déclaré',
            'RequiringController réclame un droit sans être un cas d\'usage',
            'TestsWithoutRequiringUseCase déclare fixture.invoice.view sans jamais le réclamer',
        ], self::violationsInFixtures());
    }

    /**
     * Un droit choisi par une valeur est bien réclamé — le geste est gouverné — mais rien ne
     * peut le rapprocher de ce que l'attribut déclare. Le dire ainsi plutôt que d'accuser la
     * classe de ne jamais réclamer ce qu'elle déclare : l'accusation serait fausse.
     */
    public function testAComputedPermissionIsNamedForWhatItIs(): void
    {
        $violations = self::violationsInFixtures();

        self::assertContains(
            'ComputesItsPermissionUseCase réclame un droit par une valeur, que ce contrôle ne sait pas rapprocher de ses déclarations',
            $violations,
        );
        self::assertNotContains(
            'ComputesItsPermissionUseCase déclare fixture.invoice.view sans jamais le réclamer',
            $violations,
        );
    }

    /**
     * La faiblesse que ce contrôle répare : les trois refus de compilation ne jugent que ce
     * qui implémente le marqueur. Une classe qui l'oublie tout en réclamant des droits leur
     * échappe entièrement — son geste n'entre dans aucun inventaire et n'est gouverné par
     * rien.
     */
    public function testItCatchesAPermissionDemandedOutsideAUseCase(): void
    {
        self::assertContains(
            'RequiringController réclame un droit sans être un cas d\'usage',
            self::violationsInFixtures(),
        );
    }

    /**
     * Une implémentation du contrat porte `require()` : elle ne l'usurpe pas, et ne doit pas
     * être confondue avec une surface qui contrôlerait à la place du verbe.
     */
    public function testAnAuthorizerIsNotMistakenForAnOffender(): void
    {
        self::assertNotContains(
            'FixedAuthorizer réclame un droit sans être un cas d\'usage',
            self::violationsInFixtures(),
        );
    }

    /**
     * Tester un droit sans s'y tenir porte le même nom qu'y tenir, à un mot près. Chercher le
     * nom du droit plutôt que l'appel laisserait passer cette écriture-là.
     */
    public function testItCatchesAPermissionTestedButNeverEnforced(): void
    {
        self::assertContains(
            'TestsWithoutRequiringUseCase déclare fixture.invoice.view sans jamais le réclamer',
            self::violationsInFixtures(),
        );
    }

    /**
     * Le sens inverse : un droit exigé sans être déclaré n'entre dans aucun inventaire, ne
     * peut être accordé à personne, et ferme le verbe pour tout le monde.
     */
    public function testItCatchesAPermissionDemandedWithoutBeingDeclared(): void
    {
        self::assertContains(
            'DemandsUndeclaredUseCase réclame InvoicePermission::Create sans l\'avoir déclaré',
            self::violationsInFixtures(),
        );
    }

    /**
     * L'attribut posé hors d'un cas d'usage : le conteneur ne voit que les services, une
     * entité ou un objet du domaine y échapperaient.
     */
    public function testItCatchesADeclarationOutsideAUseCase(): void
    {
        self::assertContains(
            'DeclaringController déclare un droit sans être un cas d\'usage',
            self::violationsInFixtures(),
        );
    }

    /**
     * La faute la plus dangereuse qu'un contrôle puisse commettre : ouvrir en croyant fermer.
     *
     * Une garde mise en commentaire laisse le texte cherché dans le fichier. Une lecture brute
     * du corps certifierait donc que le droit est réclamé pendant que le verbe s'exécute sans
     * aucun arbitrage — et le geste est celui de n'importe quel débogage.
     */
    public function testAGuardLeftInACommentIsNotAGuard(): void
    {
        self::assertContains(
            'CommentedGuardUseCase déclare fixture.invoice.create sans jamais le réclamer',
            self::violationsInFixtures(),
        );
    }

    /**
     * Le piège que ce détecteur existe pour éviter : la ligne d'attribut porte elle-même le
     * texte qu'une lecture naïve chercherait, et une telle lecture serait donc toujours
     * satisfaite — y compris ici, où le corps ne réclame rien.
     */
    public function testReadingTheWholeFileWouldProveNothing(): void
    {
        $file = new \ReflectionClass(DeclaresWithoutDemandingUseCase::class)->getFileName();
        self::assertNotFalse($file);

        self::assertStringContainsString('InvoicePermission::Create', (string) file_get_contents($file));
        self::assertNotSame([], self::violationsInFixtures());
    }

    /** Filet du filet : un balayage qui ne trouve plus aucune classe passerait à vide. */
    public function testTheScanReachesTheUseCases(): void
    {
        self::assertContains(
            'DeclaresWithoutDemandingUseCase déclare fixture.invoice.create sans jamais le réclamer',
            self::violationsInFixtures(),
        );
    }

    /** @return list<string> */
    private static function violationsInFixtures(): array
    {
        return PermissionUsage::violationsUnder(\dirname(__DIR__).'/Fixture', 'ArnaudMoncondhuy\\Authorization\\Tests\\Fixture\\');
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2).'/src';
    }
}
