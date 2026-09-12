# Live MODX tests (modxkit/testbench)

PHPUnit suite on a real MODX 3 kernel. Stub suites (`tests/Unit`, `tests/Integration`, `tests/WebApi`, smoke `tests/*Test.php`) stay on `phpunit.xml.dist` and do **not** boot MODX.

## What runs where

| Command | Kernel | Database |
| --- | --- | --- |
| `composer test:smoke` | no | no |
| `composer test` / `test:unit` / `test:integration` / `test:web-api` | stubs | optional PDO via `MS3_TEST_MYSQL_*` |
| `composer test:modx` | live MODX 3.1+ / 3.2 | MySQL via `MODX_TESTBENCH_DB_*` |

`composer ci:php` does **not** include `test:modx`. That job stays green without a MODX install.

## Requirements

- PHP 8.2–8.4, extensions `pdo_mysql`, `zip`, and `gd` (or `imagick` for `ms3_image`)
- MySQL 8 / MariaDB 10.11
- MODX **3.1.2-pl**, **3.2.3-pl**, or **3.2.4-pl** (testbench default `3.2.3-pl`)

MODX 3.0.x is not supported by [modxkit/testbench](https://github.com/modxkit/testbench): the core cannot boot in API mode (`reloadConfig()` redeclares model classes). MiniShop3 still documents 3.0.0+ for shops; this suite does not promise 3.0. See [modxcms/revolution#17020](https://github.com/modxcms/revolution/issues/17020).

## Local run

```bash
export MODX_TESTBENCH_DB_HOST=127.0.0.1
export MODX_TESTBENCH_DB_USER=root
export MODX_TESTBENCH_DB_PASS=secret

cd core/components/minishop3
composer install
vendor/bin/modx-testbench install
composer test:modx
```

First install downloads MODX into `~/.cache/modx-testbench/`. Later runs reuse that cache.

```bash
vendor/bin/modx-testbench status
vendor/bin/modx-testbench destroy
```

Do not run stub PHPUnit and `test:modx` in one PHP process (different bootstraps).

## Schema source: Phinx migrations (#695)

Live tests do **not** call `PackageDefinition::tables()`. Schema and seed data come from the same Phinx path as a real install (`resolver_02_migrations.php` → `phinx.php` → `Manager::migrate('production')`).

How it runs in testbench:

1. `ExtraTestCase` calls `PhinxSchemaBootstrap::ensure()` before opening snapshot isolation and sets `ms3_core_path` to the checkout core.
2. On the **first** test of a process it runs all pending migrations (no-op when already applied), then **always** recaptures the RefreshesDatabase baseline so snapshot and DB cannot diverge after a crash between migrate and capture.
3. Migrations that need xPDO Manager (`initial_schema`, `create_option_groups_and_migrate`, `repair_grid_fields_if_missing`) resolve `$modx` via `migrations/_modx.php` (injected instance first, `config.core.php` fallback for CLI on a real site).
4. The lock `table_count` is synced via `SchemaInventory::countTablesWithPrefix()` so the next process does not reinstall.

CI job `modx-testbench` already runs `composer test:modx` with a non-empty `MODX_TESTBENCH_DB_PREFIX` (`modx_`). `PhinxSchemaLiveTest` asserts seeds, bidirectional map↔table columns, and a second `migrate()` without duplicate rows.

All other live tests in this suite share that post-migration snapshot through `ExtraTestCase`.

To force a clean reinstall (drop migrated baseline): `MODX_TESTBENCH_FORCE_INSTALL=1 vendor/bin/modx-testbench install`.

## Layout

- `Support/ExtraTestCase.php` — `PackageDefinition` without `->tables()`, delegates schema to `PhinxSchemaBootstrap`
- `Support/PhinxSchemaBootstrap.php` — once-per-process migrate + snapshot recapture + lock sync
- `Support/PackageModels.php` — xPDO table classes for schema comparison (`msProduct` / `msCategory` are separate because they extend `modResource`)
- `PhinxSchemaLiveTest.php` — migration outcome checks (#695)
- `phpunit.modx.xml` — testbench bootstrap, suite `Modx`

The suite covers schema (`SHOW TABLES`), persist for every MiniShop3 table class, product/category resources plus composite rows, GetList/Create/Enable/Disable processors, DI `has()`/`get()` for every default service, extra-field `loadMap()`, customer `RegisterService`, settings, and plugin events (`msOnSaveOrder`, `msOnVendorCreate`).

Processors are PSR-4 classes under `src/Processors/`. Address them by FQCN (`Create::class`). A string action without `processors_path` is resolved against core processors and fails with “Requested processor not found”.

Processor `$permission` runs through `modContext::checkPolicy()` only when the session is
`SESSION_STATE_INITIALIZED`. Testbench boots in API/CLI (`SESSION_STATE_UNAVAILABLE`), so deny
tests use `ExtraTestCase::withProcessorPoliciesEnforced()` to force INITIALIZED for the callback
and restore afterward (#696). Use `assertProcessorPermissionDenied()` — it compares the full
`permission_denied_processor` lexicon string (pass the same `action` property you give the
processor). Pass `action` so core `preg_replace` on PHP 8.4+ does not see `null`.

Non-sudo success: `grantContextPermissions($user, ['mssetting_save'])` on the live context
(`web` in testbench). Revoke with `revokeContextPermissions($policy)` — empty policy data is
allow-all in MODX, so revoke keeps a sentinel without the target key (#710).
