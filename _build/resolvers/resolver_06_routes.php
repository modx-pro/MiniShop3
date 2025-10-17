<?php

use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modX;

/**
 * Резолвер для управления файлами роутов MiniShop3
 *
 * Два типа файлов:
 * 1. ms3_routes.php - СИСТЕМНЫЕ роуты (ПЕРЕЗАПИСЫВАЮТСЯ при обновлении)
 * 2. ms3_routes.custom.php - ПОЛЬЗОВАТЕЛЬСКИЕ роуты (НЕ ТРОГАЮТСЯ никогда)
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

        // ==========================================
        // 1. СИСТЕМНЫЕ РОУТЫ (ВСЕГДА обновляются)
        // ==========================================
        $systemTarget = MODX_CORE_PATH . 'config/ms3_routes.php';
        $systemSource = $componentPath . 'config/routes.example.php';

        if (!file_exists($systemSource)) {
            $modx->log(modX::LOG_LEVEL_ERROR,
                '[MiniShop3] System routes source not found: ' . $systemSource
            );
            $success = false;
            break;
        }

        $isUpgrade = file_exists($systemTarget);

        // Копируем/перезаписываем системный файл
        if (copy($systemSource, $systemTarget)) {
            if ($isUpgrade) {
                $modx->log(modX::LOG_LEVEL_WARN,
                    '⚠️ [MiniShop3] System routes UPDATED at core/config/ms3_routes.php'
                );
                $modx->log(modX::LOG_LEVEL_WARN,
                    '   This file was OVERWRITTEN with new system routes.'
                );
                $modx->log(modX::LOG_LEVEL_INFO,
                    '   Your custom routes in ms3_routes.custom.php are safe!'
                );
            } else {
                $modx->log(modX::LOG_LEVEL_INFO,
                    '✅ [MiniShop3] System routes created at core/config/ms3_routes.php'
                );
            }
        } else {
            $modx->log(modX::LOG_LEVEL_ERROR,
                '[MiniShop3] Failed to copy system routes to: ' . $systemTarget
            );
            $success = false;
            break;
        }

        // ==========================================
        // 2. ПОЛЬЗОВАТЕЛЬСКИЕ РОУТЫ (создаём только если нет)
        // ==========================================
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
                    '✅ [MiniShop3] Custom routes file created at core/config/ms3_routes.custom.php'
                );
                $modx->log(modX::LOG_LEVEL_INFO,
                    '   This file will NEVER be overwritten. Safe to customize!'
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
        // При удалении НЕ трогаем файлы - пусть пользователь сам решает
        $modx->log(modX::LOG_LEVEL_INFO,
            '[MiniShop3] Routes files were NOT removed:'
        );
        $modx->log(modX::LOG_LEVEL_INFO,
            '   - core/config/ms3_routes.php'
        );
        $modx->log(modX::LOG_LEVEL_INFO,
            '   - core/config/ms3_routes.custom.php'
        );
        $modx->log(modX::LOG_LEVEL_INFO,
            '   Remove manually if needed.'
        );
        $success = true;
        break;
}

return $success;
