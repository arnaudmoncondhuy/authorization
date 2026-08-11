<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * Ce que l'appelant courant a le droit de faire.
 *
 * Un cas d'usage reçoit ce contrat et jamais le contrôle d'accès du framework : c'est ce qui
 * le rend atteignable par une tâche planifiée, un worker ou un test, sans requête ni session.
 *
 * Qui est l'appelant, et selon quel modèle ses droits se décident, ne regarde pas ce contrat.
 * L'implémentation le sait, le cas d'usage l'ignore.
 */
interface Authorizer
{
    public function can(Permission $permission): bool;

    /**
     * Ne rend rien quand le droit est accordé, et arrête le cas d'usage sinon.
     *
     * Porte le verbe de {@see RequiresPermission} : ce que l'attribut déclare au-dessus de la
     * classe, le corps le réclame ici, dans les mêmes mots. C'est cette égalité que
     * {@see Testing\PermissionUsage} vérifie, dans les deux sens.
     *
     * S'appelle en tête de traitement : ce qui suit ne doit être ni lu ni écrit tant que le
     * droit n'est pas acquis.
     *
     * @throws MissingPermission
     */
    public function require(Permission $permission): void;
}
