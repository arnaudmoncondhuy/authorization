<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\Tests\Fixture\Unscanned;

/**
 * Un fichier `.php` qui ne déclare aucun type, dans une arborescence PSR-4.
 *
 * Le balayage en déduit un nom de classe depuis le chemin, ne le trouve pas, et passait au
 * suivant sans rien dire. C'est le seul saut de ce contrôle qui ouvre : un cas d'usage logé
 * dans un fichier que sa place ne nomme pas — une classe renommée sans que le fichier suive —
 * échappait à toute lecture, tout en portant du métier gouverné par rien.
 */
function fixtureHelper(): string
{
    return 'ce fichier ne déclare aucune classe';
}
