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

// `config/` en fait partie, et ce n'est pas un détail : c'est le fichier qui câble les trois
// services du paquet. Tant qu'il est resté hors du finder, la règle d'ordre des imports
// commentée ci-dessous n'avait jamais lu la seule chose qu'un installateur ouvre.
$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/../src', __DIR__.'/../tests', __DIR__.'/../config'])
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
        //
        // `imports_order` est redit alors qu'il vaut déjà cela dans @Symfony : ce tableau
        // REMPLACE celui du jeu de règles, il ne s'y ajoute pas. Ne donner que
        // `sort_algorithm` remettait l'ordre des groupes à nul, et les `use function` se
        // retrouvaient triés au milieu des classes — ce que le reste du dépôt n'écrit nulle
        // part.
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
    ])
    ->setCacheFile(__DIR__.'/../var/php-cs-fixer.cache')
    ->setFinder($finder)
;
