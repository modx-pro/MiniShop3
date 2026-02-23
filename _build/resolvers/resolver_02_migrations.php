<?php
/**
 * Resolver for running Phinx migrations
 *
 * This resolver executes database migrations using Phinx library programmatically
 * Works on shared hosting without CLI access
 */

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modX;

/**
 * Check MySQL connection and reconnect if lost.
 *
 * Between resolvers, MySQL connection may die due to wait_timeout
 * (e.g. after long package downloads in resolver_01).
 * xPDO doesn't auto-reconnect because dead PDO object !== null.
 */
function ms3ReconnectMigrations(modX $modx): void
{
    if ($modx->pdo === null) {
        $modx->connect();
        return;
    }
    try {
        $result = @$modx->pdo->query('SELECT 1');
        if ($result !== false) {
            return;
        }
    } catch (\PDOException $e) {
        // Connection lost
    }
    $modx->log(modX::LOG_LEVEL_WARN, '[MiniShop3] DB connection lost, reconnecting...');
    $modx->pdo = null;
    if ($modx->connection) {
        $modx->connection->pdo = null;
    }
    $modx->connect();
}

/** @var xPDOTransport $transport */
/** @var array $options */
/** @var modX $modx */

if ($transport->xpdo) {
    $modx = $transport->xpdo;

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:

            // Увеличиваем лимиты для миграций
            @ini_set('max_execution_time', 300);  // 5 минут
            @ini_set('memory_limit', '256M');

            $componentPath = MODX_CORE_PATH . 'components/minishop3/';
            $vendorAutoload = $componentPath . 'vendor/autoload.php';
            $phinxConfig = $componentPath . 'phinx.php';

            // Проверка наличия Phinx
            if (!file_exists($vendorAutoload)) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Phinx vendor/autoload.php not found at: ' . $vendorAutoload);
                $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Please run "composer install" in: ' . $componentPath);
                break;
            }

            if (!file_exists($phinxConfig)) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Phinx config not found at: ' . $phinxConfig);
                break;
            }

            // Reconnect after potential long gap from resolver_01 (package downloads, up to 180s)
            ms3ReconnectMigrations($modx);

            try {
                // Загрузка Composer autoload (только если ещё не загружен)
                if (!class_exists('Phinx\\Config\\Config')) {
                    require_once $vendorAutoload;
                }

                // Загрузка конфигурации Phinx
                // $modx автоматически доступен в scope загружаемого файла
                $configArray = require $phinxConfig;

                // Проверяем корректность конфигурации
                if (!isset($configArray['paths']['migrations'])) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Invalid Phinx config: missing migrations path');
                    break;
                }

                $config = new Config($configArray);

                // Создание input и output для Phinx
                $input = new StringInput('');
                $output = new BufferedOutput();

                // Создание менеджера миграций
                $manager = new Manager($config, $input, $output);

                $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Starting database migrations...');

                // Выполнение всех pending миграций
                try {
                    $manager->migrate('production');
                } catch (Exception $migrateEx) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Migration execution failed: ' . $migrateEx->getMessage());
                    throw $migrateEx;
                }

                // Получение вывода Phinx
                $outputText = $output->fetch();
                if (!empty($outputText)) {
                    foreach (explode("\n", $outputText) as $line) {
                        if (!empty(trim($line))) {
                            $modx->log(modX::LOG_LEVEL_INFO, '  ' . $line);
                        }
                    }
                }

                $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Database migrations completed');

            } catch (Exception $e) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Migration error: ' . $e->getMessage());
                $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Stack trace: ' . $e->getTraceAsString());

                // Не прерываем установку, логируем ошибку
                // В production можно добавить уведомление администратору
            }

            // Reconnect after potentially long Phinx execution for subsequent resolvers (03-08)
            ms3ReconnectMigrations($modx);

            break;

        case xPDOTransport::ACTION_UNINSTALL:
            // При удалении компонента можно откатить миграции
            // Но обычно оставляют данные для безопасности

            $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Database tables are preserved during uninstall');
            $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] To remove tables manually, use: DROP TABLE ms3_*');

            break;
    }
}

return true;
