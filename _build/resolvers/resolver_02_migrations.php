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

            try {
                // Загрузка Composer autoload (только если ещё не загружен)
                if (!class_exists('Phinx\\Config\\Config')) {
                    require_once $vendorAutoload;
                }

                // Загрузка конфигурации Phinx
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

                $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] ===== Starting database migrations =====');
                $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Migrations path: ' . $configArray['paths']['migrations']);

                // Выполнение всех pending миграций
                try {
                    $manager->migrate('production');
                    $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Migrate command executed');
                } catch (Exception $migrateEx) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Migration execution failed: ' . $migrateEx->getMessage());
                    throw $migrateEx;
                }

                // Получение вывода Phinx
                $outputText = $output->fetch();
                if (!empty($outputText)) {
                    $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Phinx output:');
                    foreach (explode("\n", $outputText) as $line) {
                        if (!empty(trim($line))) {
                            $modx->log(modX::LOG_LEVEL_INFO, '  ' . $line);
                        }
                    }
                } else {
                    $modx->log(modX::LOG_LEVEL_WARN, '[MiniShop3] Phinx produced no output');
                }

                $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] ===== Database migrations completed =====');

            } catch (Exception $e) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Migration error: ' . $e->getMessage());
                $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Stack trace: ' . $e->getTraceAsString());

                // Не прерываем установку, логируем ошибку
                // В production можно добавить уведомление администратору
            }

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
