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

- PHP 8.2–8.4, extensions `pdo_mysql` and `zip`
- MySQL 8 / MariaDB 10.11
- MODX **3.1.2-pl** or **3.2.3-pl** (testbench default `3.2.3-pl`)

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

## Layout

- `Support/ExtraTestCase.php` — `PackageDefinition` matching `bootstrap.php`, `ServiceRegistry` via `ms3`
- `phpunit.modx.xml` — testbench bootstrap, suite `Modx`

Schema for this suite comes from `PackageDefinition::tables()` (xPDO `createObjectContainer`). Full Phinx migrate is not run here: `InitialSchema` boots a second kernel from `config.core.php` at the extra root, which is not a MODX install in git. `phinx.php` is still checked against the live `$modx` connection. A later change can inject that `$modx` into `InitialSchema`.

Processors are PSR-4 classes under `src/Processors/`. Address them by FQCN (`Create::class`). A string action without `processors_path` is resolved against core processors and fails with “Requested processor not found”.
