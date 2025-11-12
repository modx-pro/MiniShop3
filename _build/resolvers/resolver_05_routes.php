<?php

use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modX;

/**
 * Резолвер для создания файлов пользовательских роутов
 *
 * Системные роуты:
 * - core/components/minishop3/config/routes/manager.php - Manager API (админка)
 * - core/components/minishop3/config/routes/web.php - Web API (фронтенд)
 *
 * Пользовательские роуты создаются в:
 * - core/config/ms3_routes_manager.custom.php - переопределения Manager API
 * - core/config/ms3_routes_web.custom.php - переопределения Web API
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

        // ============================================
        // 1. Создаём файл Manager API custom routes
        // ============================================
        $managerCustomTarget = MODX_CORE_PATH . 'config/ms3_routes_manager.custom.php';
        $managerCustomSource = $componentPath . 'config/routes_manager.custom.example.php';

        if (file_exists($managerCustomTarget)) {
            // Файл уже существует - НЕ трогаем!
            $modx->log(modX::LOG_LEVEL_INFO,
                '✅ [MiniShop3] Manager API custom routes file exists (preserved): core/config/ms3_routes_manager.custom.php'
            );
        } else {
            // Создаём файл впервые
            if (file_exists($managerCustomSource) && copy($managerCustomSource, $managerCustomTarget)) {
                $modx->log(modX::LOG_LEVEL_INFO,
                    '✅ [MiniShop3] Manager API custom routes file created at: core/config/ms3_routes_manager.custom.php'
                );
                $modx->log(modX::LOG_LEVEL_INFO,
                    '   This file will NEVER be overwritten. Safe to customize!'
                );
            } else {
                // Не критично - файл опциональный
                $modx->log(modX::LOG_LEVEL_WARN,
                    '[MiniShop3] Could not create Manager API custom routes example (optional)'
                );
            }
        }

        // ============================================
        // 2. Создаём файл Web API custom routes
        // ============================================
        $webCustomTarget = MODX_CORE_PATH . 'config/ms3_routes_web.custom.php';
        $webCustomSource = $componentPath . 'config/routes_web.custom.example.php';

        if (file_exists($webCustomTarget)) {
            // Файл уже существует - НЕ трогаем!
            $modx->log(modX::LOG_LEVEL_INFO,
                '✅ [MiniShop3] Web API custom routes file exists (preserved): core/config/ms3_routes_web.custom.php'
            );
        } else {
            // Создаём файл впервые
            if (file_exists($webCustomSource) && copy($webCustomSource, $webCustomTarget)) {
                $modx->log(modX::LOG_LEVEL_INFO,
                    '✅ [MiniShop3] Web API custom routes file created at: core/config/ms3_routes_web.custom.php'
                );
                $modx->log(modX::LOG_LEVEL_INFO,
                    '   This file will NEVER be overwritten. Safe to customize!'
                );
            } else {
                // Не критично - файл опциональный
                $modx->log(modX::LOG_LEVEL_WARN,
                    '[MiniShop3] Could not create Web API custom routes example (optional)'
                );
            }
        }

        $modx->log(modX::LOG_LEVEL_INFO,
            '📁 [MiniShop3] System routes are in: core/components/minishop3/config/routes/'
        );

        break;

    case xPDOTransport::ACTION_UNINSTALL:
        // При удалении НЕ трогаем файлы - пусть пользователь сам решает
        $modx->log(modX::LOG_LEVEL_INFO,
            '[MiniShop3] Custom routes files were NOT removed:'
        );
        $modx->log(modX::LOG_LEVEL_INFO,
            '   - core/config/ms3_routes_manager.custom.php'
        );
        $modx->log(modX::LOG_LEVEL_INFO,
            '   - core/config/ms3_routes_web.custom.php'
        );
        $modx->log(modX::LOG_LEVEL_INFO,
            '   Remove manually if needed.'
        );
        $success = true;
        break;
}

return $success;
