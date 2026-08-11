<?php

declare(strict_types=1);

/*
 * Style de code — une seule source de vérité pour le dépôt.
 *
 * Une seconde configuration à la racine serait trouvée par `vendor/bin/php-cs-fixer` sans
 * argument, là où check.sh désigne celle-ci : le code se formaterait alors dans un style que
 * la routine refuse.
 *
 * Le jeu de règles est @Symfony et son volet `risky`, dont `native_function_invocation` — c'est
 * lui qui impose les `\sprintf()` / `\count()` préfixés.
 */

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/../src', __DIR__.'/../tests'])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        // Le typage strict est obligatoire, pas coutumier.
        'declare_strict_types' => true,
        // Ordre des imports stable : sinon chaque outil qui ajoute un `use` le pose ailleurs
        // et le diff devient illisible.
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
    ])
    ->setCacheFile(__DIR__.'/../var/php-cs-fixer.cache')
    ->setFinder($finder)
;
