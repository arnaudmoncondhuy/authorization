<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\DependencyInjection;

/**
 * Les noms de tag que le bundle pose et que les passes relisent.
 *
 * Rassemblés ici plutôt que sur l'une des passes : trois lecteurs partagent ce nom, et le
 * loger chez l'un d'eux ferait dépendre les deux autres d'une classe dont ils n'ont rien à
 * savoir.
 */
final class Tag
{
    /**
     * Posé par autoconfiguration sur tout service qui implémente
     * {@see \ArnaudMoncondhuy\Authorization\UseCase}.
     *
     * C'est ce tag, et non un parcours de fichiers, qui désigne les cas d'usage : un parcours
     * dépendrait d'une convention de chemins et se périmerait.
     */
    public const string USE_CASE = 'authorization.use_case';

    private function __construct()
    {
    }
}
