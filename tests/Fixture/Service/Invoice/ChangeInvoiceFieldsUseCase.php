<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Service\Invoice;

use ArnaudMoncondhuy\Authorization\Authorizer;
use ArnaudMoncondhuy\Authorization\RequiresPermission;
use ArnaudMoncondhuy\Authorization\Tests\Fixture\Authorization\InvoicePermission;
use ArnaudMoncondhuy\Authorization\UseCase;

/**
 * Réclame le droit de base à l'entrée, et le droit fin au moment précis où le champ change.
 *
 * C'est le grain le plus juste qu'on puisse tenir : modifier le libellé d'une facture ne
 * réclame pas ce que réclame l'antidatage. Il impose de descendre la réclamation dans la
 * méthode qui applique, et un contrôle qui ne lirait que `__invoke()` accuserait ce cas
 * d'usage de déclarer un droit qu'il ne réclame jamais.
 *
 * @phpstan-type Change array{field: string, value: string}
 */
#[RequiresPermission(InvoicePermission::Finalize)]
#[RequiresPermission(InvoicePermission::Backdate)]
final readonly class ChangeInvoiceFieldsUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    /** @param array<string, string> $changes */
    public function __invoke(array $changes): void
    {
        $this->access->require(InvoicePermission::Finalize);

        foreach ($changes as $field => $value) {
            $this->apply($field, $value);
        }
    }

    private function apply(string $field, string $value): void
    {
        if ('backdatedTo' === $field) {
            $this->access->require(InvoicePermission::Backdate);
        }

        unset($value);
    }
}
