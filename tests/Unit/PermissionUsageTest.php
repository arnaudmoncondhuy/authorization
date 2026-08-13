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
            'DeclaringTrait déclare un droit sur un trait, où l\'attribut n\'est lu par personne',
            'DemandsUndeclaredUseCase réclame InvoicePermission::Create sans l\'avoir déclaré',
            'GovernedByAttribute déclare un droit sans être un cas d\'usage',
            'RequiringController réclame un droit sans être un cas d\'usage',
            'TestsWithoutRequiringUseCase déclare fixture.invoice.view sans jamais le réclamer',
            'Unscanned/RenamedUseCase.php n\'a pas été examiné : son chemin annonce ArnaudMoncondhuy\Authorization\Tests\Fixture\Unscanned\RenamedUseCase, que ce fichier ne déclare pas',
            'Unscanned/helpers.php n\'a pas été examiné : son chemin annonce ArnaudMoncondhuy\Authorization\Tests\Fixture\Unscanned\helpers, que ce fichier ne déclare pas',
        ], self::violationsInFixtures());
    }

    /**
     * Le saut que rien ne disait. Une classe renommée sans que son fichier suive n'était plus
     * atteinte par aucune lecture — ni son attribut, ni son corps — tout en portant un verbe
     * métier. C'est le fichier qui est nommé, et non la classe : la classe, justement, on ne
     * sait pas qu'elle est là.
     */
    public function testAFileThatDoesNotDeclareWhatItsPathAnnouncesIsReported(): void
    {
        self::assertContains(
            'Unscanned/RenamedUseCase.php n\'a pas été examiné : son chemin annonce ArnaudMoncondhuy\Authorization\Tests\Fixture\Unscanned\RenamedUseCase, que ce fichier ne déclare pas',
            self::violationsInFixtures(),
        );
    }

    /**
     * Le fichier est lu, jamais chargé, tant qu'il ne tient pas la promesse de son chemin.
     *
     * `class_exists()` déclenche l'autoloader, qui inclut le fichier désigné par le chemin.
     * Sur un fichier qui ne déclare pas la classe attendue, l'inclusion a lieu quand même et
     * rien n'empêche de la refaire : deux lectures d'une même arborescence dans un seul
     * processus tombaient sur « Cannot redeclare ». Un script égaré aurait, lui, été exécuté
     * par un contrôle censé se contenter de lire.
     */
    public function testAFileThatDoesNotHoldItsPromiseIsNeverIncluded(): void
    {
        self::violationsInFixtures();
        self::violationsInFixtures();

        self::assertFalse(
            \function_exists('ArnaudMoncondhuy\\Authorization\\Tests\\Fixture\\Unscanned\\fixtureHelper'),
            'Le balayage a inclus un fichier dont le chemin ne mène à aucun type.',
        );
    }

    /**
     * Les interfaces entrent désormais dans la lecture. `class_exists()` rend faux pour une
     * interface : tant que le balayage s'en remettait à lui, un attribut posé là n'était lu par
     * personne — ni par les passes, qui ne jugent que des services, ni par ce contrôle, qui ne
     * l'atteignait pas.
     */
    public function testAnInterfaceCarryingTheAttributeIsSeen(): void
    {
        self::assertContains(
            'GovernedByAttribute déclare un droit sans être un cas d\'usage',
            self::violationsInFixtures(),
        );
    }

    /**
     * Un attribut posé sur un trait est inerte : `getAttributes()` sur la classe qui l'emploie
     * ne le rend pas. Le droit n'entre donc dans aucun inventaire et le verbe se ferme pour
     * tout le monde, sans qu'une seule erreur soit levée.
     */
    public function testAnAttributeOnATraitIsNamedForBeingInert(): void
    {
        self::assertContains(
            'DeclaringTrait déclare un droit sur un trait, où l\'attribut n\'est lu par personne',
            self::violationsInFixtures(),
        );
    }

    /**
     * Le corps d'un trait n'est pas relu pour lui-même : ses méthodes sont déjà lues à travers
     * la classe qui les emploie, où `getDeclaringClass()` les rattache. L'accuser une seconde
     * fois nommerait la même ligne deux fois, et la seconde à tort.
     */
    public function testATraitBodyIsNotJudgedTwice(): void
    {
        self::assertNotContains(
            'DeclaringTrait réclame un droit sans être un cas d\'usage',
            self::violationsInFixtures(),
        );
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
     * Le motif le plus fin qui soit, et il ne doit pas être accusé : réclamer le droit fin au
     * moment précis où le champ change impose de descendre la réclamation dans la méthode qui
     * applique. Un contrôle qui ne lirait que `__invoke()` y verrait un droit déclaré et
     * jamais réclamé.
     */
    public function testAPermissionDemandedInAPrivateMethodIsSeen(): void
    {
        self::assertNotContains(
            'ChangeInvoiceFieldsUseCase déclare fixture.invoice.backdate sans jamais le réclamer',
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
