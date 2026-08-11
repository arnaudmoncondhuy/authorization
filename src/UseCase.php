<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization;

/**
 * Un verbe métier, atteignable par n'importe quelle surface.
 *
 * Une classe qui l'implémente :
 *
 * - porte {@see RequiresPermission} au niveau classe, autant de fois qu'elle exige de droits ;
 * - n'expose qu'une seule méthode publique, `__invoke()`, aux paramètres typés ;
 * - appelle {@see Authorizer::require()} en tête, avant toute lecture et toute écriture.
 *
 * Une surface — page, API, outil, console, worker — l'appelle et n'en redéclare jamais les
 * droits. C'est ce qui fait qu'un même verbe garde les mêmes droits par quelque porte qu'on
 * l'atteigne : il n'y a qu'un endroit où ils sont écrits.
 */
interface UseCase
{
}
