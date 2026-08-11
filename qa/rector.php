<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

/**
 * Modernisation automatique du code.
 *
 * Rector n'est jamais bloquant : check.sh signale ce qu'il propose, sans rien appliquer.
 * L'application passe par `./qa/run-rector.sh --apply`, qui demande confirmation.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/../src',
        __DIR__.'/../tests',
    ])
    ->withCache(
        cacheDirectory: '/tmp/rector-cache-authorization',
    )
    ->withSets([
        // Le paquet vise 8.4 : la montée s'arrête là, sinon Rector proposerait des écritures
        // que la version minimale déclarée ne comprend pas.
        LevelSetList::UP_TO_PHP_84,
        // Simplifications idiomatiques : ternaire, retour précoce.
        SetList::CODE_QUALITY,
        // Code mort : paramètres non utilisés, branches inatteignables.
        SetList::DEAD_CODE,
    ]);
