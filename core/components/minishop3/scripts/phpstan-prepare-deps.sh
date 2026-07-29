#!/usr/bin/env bash
# Prepare MODX / pdoTools trees under .phpstan-deps for portable PHPStan analysis.
# Prefer MS3_PHPSTAN_MODX (+ optional MS3_PHPSTAN_PDOTOOLS); otherwise shallow-clone pinned SHAs.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# scripts/ → minishop3 → components → core → repo root
ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"
DEPS="$ROOT/.phpstan-deps"
MODX_DIR="$DEPS/modx"
PDOTOOLS_DIR="$DEPS/pdotools"
PDOTOOLS_SRC="$PDOTOOLS_DIR/core/components/pdotools/src"
PINS_FILE="$SCRIPT_DIR/phpstan-deps.pins"

# shellcheck source=phpstan-deps.pins
# Pins: MODX_REPO, MODX_REF, PDOTOOLS_REPO, PDOTOOLS_REF
source "$PINS_FILE"

mkdir -p "$DEPS"

clone_pinned() {
  local repo="$1"
  local ref="$2"
  local dest="$3"
  rm -rf "$dest"
  mkdir -p "$dest"
  git -C "$dest" init -q
  git -C "$dest" remote add origin "$repo"
  git -C "$dest" fetch --depth 1 origin "$ref"
  git -C "$dest" checkout -q FETCH_HEAD
}

git_head_matches() {
  local dir="$1"
  local ref="$2"
  [[ -d "$dir/.git" ]] || return 1
  local head
  head="$(git -C "$dir" rev-parse HEAD 2>/dev/null || true)"
  [[ "$head" == "$ref" ]]
}

install_modx_composer() {
  local dir=""
  if [[ -f "$MODX_DIR/composer.json" ]]; then
    dir="$MODX_DIR"
  elif [[ -f "$MODX_DIR/core/composer.json" ]]; then
    dir="$MODX_DIR/core"
  else
    return 0
  fi
  composer install --working-dir="$dir" --no-interaction --prefer-dist --no-progress --no-dev --no-scripts
  # Autoload must exist even with --no-scripts.
  if [[ ! -f "$dir/vendor/autoload.php" && -f "$dir/composer.json" ]]; then
    composer dump-autoload --working-dir="$dir" --no-interaction
  fi
}

modx_tree_ready() {
  [[ -d "$MODX_DIR/core/src" && -d "$MODX_DIR/core/vendor/xpdo" ]]
}

ensure_modx() {
  if [[ -n "${MS3_PHPSTAN_MODX:-}" ]]; then
    if [[ ! -d "$MS3_PHPSTAN_MODX/core/src" ]]; then
      echo "MS3_PHPSTAN_MODX must point to a MODX root with core/src (got: $MS3_PHPSTAN_MODX)" >&2
      exit 1
    fi
    rm -rf "$MODX_DIR"
    ln -sfn "$MS3_PHPSTAN_MODX" "$MODX_DIR"
    echo "OK phpstan deps: MODX → $MS3_PHPSTAN_MODX"
    return
  fi

  if modx_tree_ready && git_head_matches "$MODX_DIR" "$MODX_REF"; then
    echo "OK phpstan deps: existing $MODX_DIR @$MODX_REF"
    return
  fi

  # Cache hit with matching sources but missing vendor.
  if [[ -d "$MODX_DIR/core/src" ]] && git_head_matches "$MODX_DIR" "$MODX_REF"; then
    echo "Completing MODX composer install in cached $MODX_DIR …"
    install_modx_composer
    if modx_tree_ready; then
      echo "OK phpstan deps: cached MODX + vendor @$MODX_REF"
      return
    fi
  fi

  echo "Cloning modxcms/revolution @$MODX_REF into $MODX_DIR …"
  clone_pinned "$MODX_REPO" "$MODX_REF" "$MODX_DIR"
  install_modx_composer
  if ! modx_tree_ready; then
    echo "MODX clone incomplete (need core/src and core/vendor/xpdo)" >&2
    exit 1
  fi
  echo "OK phpstan deps: cloned MODX @$MODX_REF"
}

# Resolve a local path to the directory that contains pdoTools PHP sources (…/pdotools/src).
resolve_pdotools_src() {
  local candidate="$1"
  if [[ -d "$candidate/src" && -f "$candidate/src/CoreTools.php" ]]; then
    echo "$candidate/src"
    return
  fi
  if [[ -d "$candidate/core/components/pdotools/src" ]]; then
    echo "$candidate/core/components/pdotools/src"
    return
  fi
  return 1
}

ensure_pdotools() {
  if [[ -n "${MS3_PHPSTAN_PDOTOOLS:-}" ]]; then
    local src
    if ! src="$(resolve_pdotools_src "$MS3_PHPSTAN_PDOTOOLS")"; then
      echo "MS3_PHPSTAN_PDOTOOLS must point to a pdoTools package or component root" >&2
      exit 1
    fi
    mkdir -p "$(dirname "$PDOTOOLS_SRC")"
    rm -rf "$PDOTOOLS_SRC"
    ln -sfn "$src" "$PDOTOOLS_SRC"
    echo "OK phpstan deps: pdoTools → $src"
    return
  fi

  if [[ -f "$PDOTOOLS_SRC/CoreTools.php" ]] && git_head_matches "$PDOTOOLS_DIR" "$PDOTOOLS_REF"; then
    echo "OK phpstan deps: existing $PDOTOOLS_SRC @$PDOTOOLS_REF"
    return
  fi

  echo "Cloning modx-pro/pdoTools @$PDOTOOLS_REF into $PDOTOOLS_DIR …"
  clone_pinned "$PDOTOOLS_REPO" "$PDOTOOLS_REF" "$PDOTOOLS_DIR"
  if [[ ! -f "$PDOTOOLS_SRC/CoreTools.php" ]]; then
    echo "Cloned pdoTools but expected path missing: $PDOTOOLS_SRC/CoreTools.php" >&2
    exit 1
  fi
  echo "OK phpstan deps: cloned pdoTools @$PDOTOOLS_REF"
}

ensure_modx
ensure_pdotools
