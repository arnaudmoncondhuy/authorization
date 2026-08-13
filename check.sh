#!/bin/bash

# Routine qualité du paquet.
#
# Le script joue toutes ses étapes avant de conclure : un écart de style n'empêche pas de voir
# aussi les tests rouges. Seul le bilan final fixe le code de sortie.
#
# Tout tourne sur l'hôte : un paquet n'a ni base de données, ni serveur, ni conteneur.
#
# Usage :  ./check.sh

# ═══════════════════════════════════════════════════════════════════════════════════════════
# Configuration — la seule zone à adapter.
# ═══════════════════════════════════════════════════════════════════════════════════════════

QA_DIR="qa"

# La classe de bundle vit à la racine de src/ parce que `AbstractBundle::getPath()` y calcule
# le chemin de config/. Elle branche Symfony : le lint de pureté doit donc l'écarter.
CLASSE_DE_BUNDLE="AuthorizationBundle.php"

# Le namespace que les classes du contrat ont le droit de citer, et rien d'autre.
NAMESPACE_CONTRAT='ArnaudMoncondhuy\\Authorization\\'

# Suites PHPUnit, telles que nommées dans qa/phpunit.dist.xml.
SUITES="unitaires,integration"

# ═══════════════════════════════════════════════════════════════════════════════════════════
# Mécanique — rien à adapter en dessous.
# ═══════════════════════════════════════════════════════════════════════════════════════════

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR" || exit 1

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ ! -f vendor/autoload.php ]; then
    echo -e "${RED}✘ Dépendances absentes.${NC} Lancer ${YELLOW}composer install${NC}."
    exit 1
fi

echo -e "${YELLOW}Routine qualité${NC}"

# ── 1. Composer : cohérence du manifeste et failles connues ────────────────────────────────
echo -e "\n${YELLOW}Étape 1 : validation et audit de sécurité Composer…${NC}"
composer validate --strict --quiet && composer audit --no-interaction
COMPOSER_EXIT=$?
if [ $COMPOSER_EXIT -eq 0 ]; then
    echo -e "${GREEN}✔ Composer OK — aucune dépendance vulnérable connue.${NC}"
else
    echo -e "${RED}✘ Composer signale un problème : manifeste incohérent ou faille connue.${NC}"
fi

# ── 2. Fonctions de mise au point oubliées ─────────────────────────────────────────────────
echo -e "\n${YELLOW}Étape 2 : détection des fonctions de debug…${NC}"
DEBUG_PHP=$(grep -rE "(^|[^a-zA-Z_>])(var_dump|dump|dd)\(" src tests config \
    --include="*.php" 2>/dev/null || true)

if [ -z "$DEBUG_PHP" ]; then
    echo -e "${GREEN}✔ Aucune fonction de debug oubliée.${NC}"
    DEBUG_EXIT=0
else
    echo -e "${RED}✘ Fonctions de debug trouvées :${NC}"
    echo "$DEBUG_PHP"
    DEBUG_EXIT=1
fi

# ── 3. Style de code ───────────────────────────────────────────────────────────────────────
echo -e "\n${YELLOW}Étape 3 : style de code (PHP-CS-Fixer)…${NC}"
php -d memory_limit=512M vendor/bin/php-cs-fixer fix \
    --config="$QA_DIR/.php-cs-fixer.dist.php" --dry-run --diff
CS_EXIT=$?
if [ $CS_EXIT -eq 0 ]; then
    echo -e "${GREEN}✔ Style OK.${NC}"
else
    echo -e "${RED}✘ Écarts de style. Corriger : ${YELLOW}./$QA_DIR/cs-fix.sh${NC}"
fi

# ── 4. Analyse statique ────────────────────────────────────────────────────────────────────
echo -e "\n${YELLOW}Étape 4 : analyse statique (PHPStan)…${NC}"
php -d memory_limit=1G vendor/bin/phpstan analyse -c "$QA_DIR/phpstan.neon" --no-progress
STAN_EXIT=$?
if [ $STAN_EXIT -eq 0 ]; then
    echo -e "${GREEN}✔ PHPStan OK.${NC}"
else
    echo -e "${RED}✘ PHPStan a trouvé des erreurs. Les corriger — pas de fichier de baseline.${NC}"
fi

# ── 5. Tests ───────────────────────────────────────────────────────────────────────────────
# La suite « integration » démarre un vrai noyau : c'est elle, et elle seule, qui prouve que le
# bundle est branché. Les passes éprouvées à la main resteraient vertes même si plus rien ne
# les enregistrait.
echo -e "\n${YELLOW}Étape 5 : tests (PHPUnit — $SUITES)…${NC}"
php -d memory_limit=512M vendor/bin/phpunit -c "$QA_DIR/phpunit.dist.xml" --testsuite "$SUITES"
UNIT_EXIT=$?
if [ $UNIT_EXIT -eq 0 ]; then
    echo -e "${GREEN}✔ Tous les tests passent.${NC}"
else
    echo -e "${RED}✘ Des tests échouent.${NC}"
fi

# ── 6. Secrets ─────────────────────────────────────────────────────────────────────────────
# `detect` scanne l'historique complet, pas seulement le diff : un secret retiré d'un fichier
# reste dans les objets git.
echo -e "\n${YELLOW}Étape 6 : détection de secrets (Gitleaks)…${NC}"
GITLEAKS="$QA_DIR/bin/gitleaks"
if [ ! -x "$GITLEAKS" ]; then
    echo -e "${YELLOW}→ Installation de Gitleaks…${NC}"
    "$QA_DIR/install-gitleaks.sh" || echo -e "${RED}✘ Installation impossible.${NC}"
fi
if [ ! -x "$GITLEAKS" ]; then
    GITLEAKS_EXIT=1
elif [ -d .git ]; then
    "$GITLEAKS" detect --config "$QA_DIR/.gitleaks.toml" --no-banner --redact 2>&1 | tail -15
    GITLEAKS_EXIT=${PIPESTATUS[0]}
else
    echo -e "${YELLOW}⚠ Pas de dépôt git ici : scan indicatif de l'arborescence.${NC}"
    "$GITLEAKS" detect --no-git --source . --config "$QA_DIR/.gitleaks.toml" --no-banner --redact 2>&1 | tail -5
    GITLEAKS_EXIT=0
fi
if [ "${GITLEAKS_EXIT:-0}" -eq 0 ]; then
    echo -e "${GREEN}✔ Gitleaks OK.${NC}"
else
    echo -e "${RED}✘ Secret potentiel détecté dans l'historique.${NC}"
    echo -e "  ⚠️ Ne PAS l'ajouter à l'allowlist : le faire tourner chez son émetteur d'abord,"
    echo -e "     puis réécrire l'historique. Un secret « supprimé » reste dans les objets git."
fi

# ── 7. Frontières d'architecture ───────────────────────────────────────────────────────────
echo -e "\n${YELLOW}Étape 7 : frontières d'architecture (Deptrac)…${NC}"

# La promesse du paquet est qu'une application peut citer le contrat depuis son domaine sans y
# faire entrer Symfony. Deptrac ne peut pas la tenir seul : il ne gouverne que les couches
# qu'on lui déclare, et tout le reste du monde lui échappe — un import de framework dans une
# classe du contrat ne serait signalé par rien.
#
# Les deux écritures sont cherchées : l'import en tête de fichier, et le nom pleinement
# qualifié posé dans une signature, qui n'est pas un `use` et passerait inaperçu.
FICHIERS_PURS="$( { find src -maxdepth 1 -name '*.php' ! -name "$CLASSE_DE_BUNDLE";
                    find src/Testing -name '*.php'; } 2>/dev/null || true)"

if [ -z "$FICHIERS_PURS" ]; then
    PURETE_EXIT=0
else
    IMPORTS_ETRANGERS="$(echo "$FICHIERS_PURS" | xargs grep -hE '^use ' 2>/dev/null \
        | grep -v "^use $NAMESPACE_CONTRAT" || true)"
    NOMS_ETRANGERS="$(echo "$FICHIERS_PURS" | xargs grep -hoE '(^|[^A-Za-z0-9_\\])\\[A-Z][A-Za-z0-9_]*\\' 2>/dev/null \
        | grep -oE '\\[A-Z][A-Za-z0-9_]*\\' | grep -v '^\\ArnaudMoncondhuy\\$' | sort -u || true)"

    if [ -n "$IMPORTS_ETRANGERS" ] || [ -n "$NOMS_ETRANGERS" ]; then
        PURETE_EXIT=1
        echo -e "${RED}✘ Le contrat connaît autre chose que lui-même :${NC}"
        [ -n "$IMPORTS_ETRANGERS" ] && echo "$IMPORTS_ETRANGERS" | sed 's/^/    /'
        [ -n "$NOMS_ETRANGERS" ] && echo "$NOMS_ETRANGERS" | sed 's/^/    nom qualifié /'
        echo -e "  Ce qui réclame une technique vit dans Bridge/ ou DependencyInjection/."
        echo -e "  Une application doit pouvoir citer le contrat depuis son domaine."
    else
        PURETE_EXIT=0
    fi
fi

php -d error_reporting="E_ALL&~E_DEPRECATED" vendor/bin/deptrac analyse \
    --config-file="$QA_DIR/deptrac.yaml" --no-progress --cache-file=var/deptrac.cache
ANALYSE_EXIT=$?
if [ $ANALYSE_EXIT -ne 0 ]; then
    echo -e "${RED}✘ Violation d'architecture. La corriger dans le code, pas dans skip_violations.${NC}"
fi

if [ $ANALYSE_EXIT -eq 0 ] && [ $PURETE_EXIT -eq 0 ]; then
    DEPTRAC_EXIT=0
    echo -e "${GREEN}✔ Architecture respectée.${NC}"
else
    DEPTRAC_EXIT=1
fi

# ── 8. Rector : INFORMATIF, ne bloque jamais ───────────────────────────────────────────────
echo -e "\n${YELLOW}Étape 8 : opportunités de modernisation (Rector, informatif)…${NC}"
RECTOR_OUT=$(php -d error_reporting="E_ALL&~E_DEPRECATED" -d memory_limit=1G vendor/bin/rector \
    process --config "$QA_DIR/rector.php" --no-progress-bar --dry-run 2>&1)
CHANGED=$(echo "$RECTOR_OUT" | grep -oE '[0-9]+ files? would have been changed' | head -1 | grep -oE '^[0-9]+')
if [ -z "$CHANGED" ] || [ "$CHANGED" = "0" ]; then
    echo -e "${GREEN}✔ Rien à moderniser.${NC}"
else
    echo -e "${YELLOW}ℹ️  Rector propose des améliorations sur ${CHANGED} fichier(s).${NC}"
    echo -e "${YELLOW}   Voir      : ./$QA_DIR/run-rector.sh${NC}"
    echo -e "${YELLOW}   Appliquer : ./$QA_DIR/run-rector.sh --apply${NC}"
fi

# ── Bilan ──────────────────────────────────────────────────────────────────────────────────
# Rector n'y figure pas : il propose, il ne juge pas.
echo -e "\n----------------------------------------"
if [ "${COMPOSER_EXIT:-0}" -eq 0 ] && [ "${DEBUG_EXIT:-0}" -eq 0 ] && [ "${CS_EXIT:-0}" -eq 0 ] \
   && [ "${STAN_EXIT:-0}" -eq 0 ] && [ "${UNIT_EXIT:-0}" -eq 0 ] \
   && [ "${GITLEAKS_EXIT:-0}" -eq 0 ] && [ "${DEPTRAC_EXIT:-0}" -eq 0 ]; then
    echo -e "${GREEN}TOUT EST OK — le paquet est publiable.${NC}"
    exit 0
else
    echo -e "${RED}CERTAINES ÉTAPES ONT ÉCHOUÉ — corriger avant de pousser.${NC}"
    exit 1
fi
