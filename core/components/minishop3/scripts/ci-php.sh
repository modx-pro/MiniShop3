#!/usr/bin/env bash
# PHP CI gate: syntax check + smoke + PHPUnit (no MODX/MySQL).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Root phinx*.php sit outside src/migrations/tests.
paths=(src migrations tests phinx.php phinx_mysql_charset.php)
count="$(find "${paths[@]}" -type f -name '*.php' | wc -l | tr -d ' ')"
find "${paths[@]}" -type f -name '*.php' -print0 \
  | xargs -0 -n1 php -l >/dev/null
echo "OK php -l (${count} files)"

composer test:smoke
composer test
