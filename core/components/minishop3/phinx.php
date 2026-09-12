<?php
/**
 * Phinx Configuration for MiniShop3
 *
 * This config integrates Phinx migrations with MODX database settings
 */

if (!isset($modx)) {
    $modxConfigPath = dirname(__FILE__, 4) . '/config.core.php';

    if (!file_exists($modxConfigPath)) {
        die('MODX config.core.php not found. Please ensure MODX is properly installed.');
    }

    if (!defined('MODX_CORE_PATH')) {
        require_once $modxConfigPath;
    }

    if (!defined('MODX_CORE_PATH')) {
        die('MODX_CORE_PATH not defined in config.core.php');
    }

    $modxClass = 'MODX\\Revolution\\modX';

    if (!class_exists($modxClass)) {
        require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    }

    if (!class_exists($modxClass) && class_exists('modX')) {
        $modxClass = 'modX';
    }

    $modx = new $modxClass();
    $modx->initialize('mgr');
}

// Expose the booted $modx to migrations: the three MODX-bootstrapping migrations resolve the
// instance via $GLOBALS['modx'] (see migrations/_modx.php) instead of booting a second kernel.
$GLOBALS['modx'] = $modx;

require_once __DIR__ . '/phinx_mysql_charset.php';

$dsnCharset = ms3PhinxExtractDsnCharset($modx->getOption('database_dsn', null, null));
$databaseCharset = $modx->getOption('database_charset', null, null);

if ($dsnCharset !== null) {
    $mysqlCharset = ms3PhinxNormalizeMysqlCharset($dsnCharset);
} elseif ($databaseCharset !== null && trim((string) $databaseCharset) !== '') {
    $mysqlCharset = ms3PhinxNormalizeMysqlCharset($databaseCharset);
} else {
    $mysqlCharset = ms3PhinxNormalizeMysqlCharset($modx->getOption('charset', null, 'utf8mb4'), true);
}

$mysqlCollation = $modx->getOption(
    'database_collation',
    null,
    $modx->getOption(
        'collation',
        null,
        ms3PhinxDefaultMysqlCollation($mysqlCharset)
    )
);

$dbConfig = [
    'adapter' => 'mysql',
    'host' => $modx->getOption('host', null, 'localhost'),
    'name' => $modx->getOption('dbname'),
    'user' => $modx->getOption('username'),
    'pass' => $modx->getOption('password'),
    'port' => $modx->getOption('port', null, '3306'),
    'charset' => $mysqlCharset,
    'collation' => $mysqlCollation,
    'table_prefix' => $modx->getOption('table_prefix', null, ''),
];

if ($dbConfig['table_prefix'] !== '' && $modx->pdo !== null) {
    $prefix = $dbConfig['table_prefix'];
    $hasOld = (bool) $modx->pdo->query("SHOW TABLES LIKE 'ms3_migrations'")?->fetch();
    $hasNew = (bool) $modx->pdo->query("SHOW TABLES LIKE '{$prefix}ms3_migrations'")?->fetch();

    if ($hasOld && !$hasNew) {
        $modx->pdo->exec("RENAME TABLE `ms3_migrations` TO `{$prefix}ms3_migrations`");

        $infoLogLevel = 1;
        if (class_exists(\MODX\Revolution\modX::class, false)) {
            $infoLogLevel = \MODX\Revolution\modX::LOG_LEVEL_INFO;
        } elseif (class_exists('modX', false)) {
            $infoLogLevel = modX::LOG_LEVEL_INFO;
        }

        $modx->log(
            $infoLogLevel,
            '[MiniShop3] Renamed legacy Phinx metadata table ms3_migrations -> ' . $prefix . 'ms3_migrations'
        );
    }
}

$migrationTable = $dbConfig['table_prefix'] . 'ms3_migrations';

return [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
        'seeds' => __DIR__ . '/seeds'
    ],
    'environments' => [
        'default_migration_table' => $migrationTable,
        'default_environment' => 'production',
        'production' => $dbConfig,
        'development' => $dbConfig,
    ],
    'version_order' => 'creation',
    'templates' => [
        'seedClass' => 'seeds/seed_template.php.dist'
    ]
];
