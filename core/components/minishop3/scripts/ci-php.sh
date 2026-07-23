#!/usr/bin/env bash
# PHP CI gate: syntax check + smoke tests (no MODX/MySQL).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

count="$(find src migrations tests -type f -name '*.php' | wc -l | tr -d ' ')"
find src migrations tests -type f -name '*.php' -print0 \
  | xargs -0 -n1 php -l >/dev/null
php -l phinx.php >/dev/null
echo "OK php -l (${count} under src/migrations/tests + phinx.php)"

composer test:smoke
