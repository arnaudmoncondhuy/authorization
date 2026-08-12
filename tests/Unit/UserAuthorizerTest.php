<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Unit;

use ArnaudMoncondhuy\Authorization\Bridge\SecurityUserAuthorizer;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\KnownUsers;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Security\RecordingAccessChecker;
use PHPUnit\Framework\TestCase;

/**
 * Ce qu'un autre que l'appelant a le droit de faire.
 *
 * Ce qui compte ici n'est pas la décision — elle appartient aux voters de l'application — mais
 * que la question leur soit posée telle quelle, sur le bon compte, et qu'un identifiant inconnu
 * se referme au lieu de casser.
 */
final class UserAuthorizerTest extends TestCase
{
    public function testItAnswersWhatTheApplicationGrantsToThatUser(): void
    {
        $checker = new RecordingAccessChecker('fixture.invoice.view');
        $rights = new SecurityUserAuthorizer($checker, new KnownUsers('camille'));

        self::assertTrue($rights->can('camille', InvoicePermission::View));
        self::assertFalse($rights->can('camille', InvoicePermission::Finalize));
    }

    /**
     * Le compte visé est bien celui qu'on nomme, et c'est l'**identité** du droit qui est
     * soumise — pas le nom du cas. Soumettre autre chose ferait décider sur ce qui n'est ni
     * stocké ni accordé.
     */
    public function testItAsksAboutTheNamedUserAndTheIdentityOfThePermission(): void
    {
        $checker = new RecordingAccessChecker();
        $rights = new SecurityUserAuthorizer($checker, new KnownUsers('camille', 'dominique'));

        $rights->can('dominique', InvoicePermission::Finalize);

        self::assertSame(
            [['user' => 'dominique', 'attribute' => 'fixture.invoice.finalize']],
            $checker->asked,
        );
    }

    /**
     * Sans compte, aucun droit — et la question ne casse pas. C'est une lecture : un écran
     * d'aperçu ou une file de notifications ne doit pas tomber sur une adresse périmée.
     */
    public function testAnUnknownIdentifierIsRefusedRatherThanRaised(): void
    {
        $checker = new RecordingAccessChecker('fixture.invoice.view');
        $rights = new SecurityUserAuthorizer($checker, new KnownUsers('camille'));

        self::assertFalse($rights->can('parti@exemple.fr', InvoicePermission::View));
        self::assertSame([], $checker->asked);
    }
}
