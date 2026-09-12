# Contributing to MiniShop3

## Obsolete Extra files (#704)

MODX `ACTION_UPGRADE` copies the new package tree and does **not** delete files that left the Extra. Leftover processors under `core/components/minishop3/src/Processors/` remain callable via `assets/components/minishop3/connector.php`.

When you delete a PHP file from the shipped component (`core/components/minishop3/` or `assets/components/minishop3/`), add its path to:

`core/components/minishop3/config/obsolete_package_files.php`

Paths are relative to that component root. Missing files on a site are skipped (not an error). The upgrade resolver `_build/resolvers/resolver_10_obsolete_files.php` purges the list via `MiniShop3\Utils\ObsoletePackageFiles` with path confinement under the component directory.
