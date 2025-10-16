<?php
/**
 * Phinx Configuration for MiniShop3
 *
 * This config integrates Phinx migrations with MODX database settings
 */

// Проверяем, передан ли $modx из родительского scope (резолвер)
// или нужно инициализировать (CLI режим)
if (!isset($modx)) {
    // CLI режим или первый запуск - загружаем MODX
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

    if (!class_exists('modX')) {
        require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    }

    // Инициализация MODX
    $modx = new modX();
    $modx->initialize('mgr');
}
// Иначе используем $modx из родительского scope (резолвер передал его)

// Настройки подключения к БД из MODX
$dbConfig = [
    'adapter' => 'mysql',
    'host' => $modx->getOption('host', null, 'localhost'),
    'name' => $modx->getOption('dbname'),
    'user' => $modx->getOption('username'),
    'pass' => $modx->getOption('password'),
    'port' => $modx->getOption('port', null, '3306'),
    'charset' => $modx->getOption('charset', null, 'utf8mb4'),
    'collation' => $modx->getOption('collation', null, 'utf8mb4_unicode_ci'),
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
        'development' => $dbConfig, // Можно использовать отдельную БД для разработки
    ],
    'version_order' => 'creation',
    'templates' => [
        'seedClass' => 'seeds/seed_template.php.dist'
    ]
];
