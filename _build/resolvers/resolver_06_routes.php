<?php

use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modX;

/**
 * Резолвер для создания файла пользовательских роутов
 *
 * Системные роуты находятся в: core/components/minishop3/config/routes.php
 * Пользовательские роуты создаются в: core/config/ms3_routes.custom.php
 *
 * @var xPDOTransport $transport
 * @var array $options
 * @var modX $modx
 */

if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

$modx = $transport->xpdo;
$success = true;
$componentPath = MODX_CORE_PATH . 'components/minishop3/';

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:

        // Создаём файл ПОЛЬЗОВАТЕЛЬСКИХ роутов (только при первой установке)
        $customTarget = MODX_CORE_PATH . 'config/ms3_routes.custom.php';
        $customSource = $componentPath . 'config/routes.custom.example.php';

        if (file_exists($customTarget)) {
            // Файл уже существует - НЕ трогаем!
            $modx->log(modX::LOG_LEVEL_INFO,
                '✅ [MiniShop3] Custom routes file exists (preserved): core/config/ms3_routes.custom.php'
            );
        } else {
            // Создаём файл впервые
            if (file_exists($customSource) && copy($customSource, $customTarget)) {
                $modx->log(modX::LOG_LEVEL_INFO,
                    '✅ [MiniShop3] Custom routes file created at: core/config/ms3_routes.custom.php'
                );
                $modx->log(modX::LOG_LEVEL_INFO,
                    '   This file will NEVER be overwritten. Safe to customize!'
                );
                $modx->log(modX::LOG_LEVEL_INFO,
                    '   System routes are in: core/components/minishop3/config/routes.php'
                );
            } else {
                // Не критично - файл опциональный
                $modx->log(modX::LOG_LEVEL_WARN,
                    '[MiniShop3] Could not create custom routes example (optional)'
                );
            }
        }

        break;

    case xPDOTransport::ACTION_UNINSTALL:
        // При удалении НЕ трогаем файл - пусть пользователь сам решает
        $modx->log(modX::LOG_LEVEL_INFO,
            '[MiniShop3] Custom routes file was NOT removed: core/config/ms3_routes.custom.php'
        );
        $modx->log(modX::LOG_LEVEL_INFO,
            '   Remove manually if needed.'
        );
        $success = true;
        break;
}

return $success;
