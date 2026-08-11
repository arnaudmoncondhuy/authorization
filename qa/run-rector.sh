#!/bin/bash
# Rector, à la main — check.sh ne fait que SIGNALER ses propositions, il n'applique rien.
#
#   ./qa/run-rector.sh            voir les diffs proposés (dry-run, ne modifie rien)
#   ./qa/run-rector.sh --apply    les appliquer, après confirmation explicite
#
# Rector réécrit du code. Une application non relue est exactement le « ça tourne donc c'est
# bon » que le reste de l'outillage existe pour empêcher, d'où la confirmation.

set -uo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 1

RECTOR="vendor/bin/rector"
[ -x "$RECTOR" ] || { echo "✘ Rector absent : lancer composer install."; exit 1; }

# E_DEPRECATED silencé : le binaire pré-compilé émet des avertissements sur les PHP récents.
PHP="php -d error_reporting=E_ALL&~E_DEPRECATED -d memory_limit=1G"

if [ "${1:-}" = "--apply" ]; then
    echo "Rector va MODIFIER les fichiers de src/ et tests/."
    read -r -p "Confirmer ? [y/N] " reponse
    case "$reponse" in
        [yY]) ;;
        *) echo "Annulé."; exit 0 ;;
    esac
    $PHP "$RECTOR" process --config qa/rector.php --no-progress-bar
    echo
    echo "→ Relire le diff (git diff), puis relancer ./check.sh avant de pousser."
else
    $PHP "$RECTOR" process --config qa/rector.php --no-progress-bar --dry-run
fi
