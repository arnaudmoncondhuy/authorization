#!/bin/bash
# Applique les corrections de style que check.sh se contente de signaler.
#
#   ./qa/cs-fix.sh

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 1

php -d memory_limit=512M vendor/bin/php-cs-fixer fix --config=qa/.php-cs-fixer.dist.php
