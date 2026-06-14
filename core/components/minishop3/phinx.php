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

if (!function_exists('ms3PhinxExtractDsnCharset')) {
    function ms3PhinxExtractDsnCharset(?string $dsn): ?string
    {
        if ($dsn === null || $dsn === '') {
            return null;
        }

        if (preg_match('/(?:^|;)charset=([^;]+)/i', $dsn, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}

if (!function_exists('ms3PhinxNormalizeMysqlCharset')) {
    function ms3PhinxNormalizeMysqlCharset(?string $charset, bool $preferUtf8mb4 = false): string
    {
        $normalized = strtolower(trim((string) $charset));
        $normalized = str_replace('-', '', $normalized);

        return match ($normalized) {
            '', 'utf8', 'utf8mb3' => $preferUtf8mb4 ? 'utf8mb4' : 'utf8',
            'utf8mb4' => 'utf8mb4',
            default => preg_replace('/[^a-z0-9_]/', '', $normalized) ?: 'utf8mb4',
        };
    }
}

if (!function_exists('ms3PhinxDefaultMysqlCollation')) {
    function ms3PhinxDefaultMysqlCollation(string $charset): string
    {
        return match ($charset) {
            'utf8' => 'utf8_general_ci',
            'utf8mb4' => 'utf8mb4_unicode_ci',
            default => $charset . '_general_ci',
        };
    }
}

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

return [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
        'seeds' => __DIR__ . '/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'ms3_migrations',
        'default_environment' => 'production',
        'production' => $dbConfig,
        'development' => $dbConfig,
    ],
    'version_order' => 'creation',
    'templates' => [
        'seedClass' => 'seeds/seed_template.php.dist'
    ]
];
