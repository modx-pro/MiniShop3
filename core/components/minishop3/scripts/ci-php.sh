#!/usr/bin/env bash
# PHP CI gate: syntax check + smoke + PHPUnit.
# MySQL Level-2 tests (@group mysql) run when MS3_TEST_MYSQL_DSN is set (CI service).
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
# Unit + Integration + WebApi (WebApi excluded from Integration directory to avoid suite overlap)
composer test
