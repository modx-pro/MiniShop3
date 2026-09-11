<?php

/**
 * Self-contained MODX bootstrap helper for Phinx migrations.
 *
 * Migrations must not import MiniShop3 application code (transport install runs Phinx before the
 * new component classes are autoloaded). This helper keeps that contract: no `use MiniShop3\`.
 *
 * Resolution order for the MODX instance:
 *  1. A pre-set $GLOBALS['modx'] — injected by phinx.php (CLI on a real site) or by the testbench
 *     live suite (see tests/Modx/Support/ExtraTestCase.php). Reusing the booted kernel avoids a
 *     second modX instance and its separate connection in the same process.
 *  2. CLI fallback: boot MODX from config.core.php at the extra root, for `vendor/bin/phinx` on a
 *     real MODX install where no $modx is in scope yet.
 *
 * Resolution order for the model path (addPackage):
 *  1. The `ms3_core_path` system setting — set by the transport resolver on a real install and
 *     injected via $modx->setOption() by the testbench hook before Phinx runs.
 *  2. Fallback: MODX_CORE_PATH . 'components/minishop3/src/Model/' — the standard install layout.
 */

if (!function_exists('ms3MigrationModx')) {
    function ms3MigrationModx(): ?\MODX\Revolution\modX
    {
        if (isset($GLOBALS['modx']) && $GLOBALS['modx'] instanceof \MODX\Revolution\modX) {
            return $GLOBALS['modx'];
        }

        $modxConfigPath = dirname(__FILE__, 5) . '/config.core.php';
        if (!file_exists($modxConfigPath)) {
            return null;
        }

        require_once $modxConfigPath;
        if (!defined('MODX_CORE_PATH')) {
            return null;
        }

        require_once MODX_CORE_PATH . 'vendor/autoload.php';
        require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

        $modxClass = class_exists(\MODX\Revolution\modX::class, false)
            ? \MODX\Revolution\modX::class
            : (class_exists('modX', false) ? 'modX' : \MODX\Revolution\modX::class);

        $modx = new $modxClass();
        $modx->initialize('mgr');

        $GLOBALS['modx'] = $modx;

        return $modx;
    }
}

if (!function_exists('ms3MigrationModelPath')) {
    function ms3MigrationModelPath(\MODX\Revolution\modX $modx): string
    {
        $core = $modx->getOption('ms3_core_path', null, null);
        if (is_string($core) && $core !== '') {
            $path = rtrim($core, '/') . '/src/Model/';
            if (is_dir($path)) {
                return $path;
            }
        }

        return MODX_CORE_PATH . 'components/minishop3/src/Model/';
    }
}

if (!function_exists('ms3MigrationAddPackage')) {
    /**
     * Registers the MiniShop3 xPDO model on $modx using the resolved model path. Idempotent: a
     * repeated addPackage() in the same process is a no-op for xPDO.
     */
    function ms3MigrationAddPackage(\MODX\Revolution\modX $modx): string
    {
        $modelPath = ms3MigrationModelPath($modx);
        $modx->addPackage('MiniShop3\\Model', $modelPath, null, 'MiniShop3\\');

        return $modelPath;
    }
}

if (!function_exists('ms3MigrationBootstrap')) {
    function ms3MigrationBootstrap(): ?\MODX\Revolution\modX
    {
        $modx = ms3MigrationModx();
        if ($modx === null) {
            return null;
        }

        ms3MigrationAddPackage($modx);

        return $modx;
    }
}
