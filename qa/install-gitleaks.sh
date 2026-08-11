#!/bin/bash
# Installe Gitleaks dans qa/bin/ — binaire gitignoré, version ÉPINGLÉE.
#
# Épinglée et non « latest » : un scanner de secrets qui change de version tout seul peut
# changer de règles tout seul, et un push qui passait hier échouerait aujourd'hui sans que rien
# n'ait bougé dans le code. La montée de version est un geste explicite.

set -euo pipefail

VERSION="8.30.1"
QA_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CIBLE="$QA_DIR/bin/gitleaks"

# Déjà à la bonne version ? On ne retélécharge rien.
if [ -x "$CIBLE" ] && "$CIBLE" version 2>/dev/null | grep -q "$VERSION"; then
    echo "✔ Gitleaks $VERSION déjà installé."
    exit 0
fi

case "$(uname -m)" in
    x86_64)  ARCH=x64 ;;
    aarch64|arm64) ARCH=arm64 ;;
    *) echo "✘ Architecture non gérée : $(uname -m)"; exit 1 ;;
esac

URL="https://github.com/gitleaks/gitleaks/releases/download/v${VERSION}/gitleaks_${VERSION}_linux_${ARCH}.tar.gz"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "→ Téléchargement de Gitleaks $VERSION ($ARCH)…"
curl -fsSL "$URL" -o "$TMP/gitleaks.tar.gz"
tar -xzf "$TMP/gitleaks.tar.gz" -C "$TMP" gitleaks

mkdir -p "$QA_DIR/bin"
mv "$TMP/gitleaks" "$CIBLE"
chmod +x "$CIBLE"

echo "✔ Gitleaks installé : $CIBLE ($("$CIBLE" version))"
