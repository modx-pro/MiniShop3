<?php
/**
 * СИСТЕМНЫЕ API Routes для MiniShop3
 *
 * Этот файл является частью компонента и обновляется вместе с ним.
 *
 * ❌ НЕ редактируйте этот файл напрямую!
 * ✅ Для своих роутов используйте: core/config/ms3_routes.custom.php
 *
 * Пользовательские роуты загружаются ПОСЛЕ системных и могут их переопределять.
 *
 * Доступ к переменным:
 * @var \MiniShop3\Router\Router $router
 * @var \MODX\Revolution\modX $modx
 *
 * @version 1.0.0
 */

use MiniShop3\Router\Middleware\AuthMiddleware;
use MiniShop3\Router\Middleware\PermissionMiddleware;
use MiniShop3\Router\Response;

// ============================================
// API для админки (Manager)
// ============================================
$router->group('/api/mgr', function($router) use ($modx) {

    // Пример простого роута с замыканием
    $router->get('/health', function() use ($modx) {
        return Response::success([
            'status' => 'ok',
            'version' => $modx->getOption('ms3_version', null, '1.0.0'),
            'timestamp' => time()
        ]);
    });

    // Пример защищённого роута (требуется авторизация в mgr)
    $router->get('/user/info', function() use ($modx) {
        if (!$modx->user || !$modx->user->isAuthenticated('mgr')) {
            return Response::error('Unauthorized', 401);
        }

        return Response::success([
            'id' => $modx->user->get('id'),
            'username' => $modx->user->get('username'),
            'fullname' => $modx->user->get('fullname')
        ]);
    })->middleware(new AuthMiddleware($modx, 'mgr'));

    // Группа роутов для конфигурации
    $router->group('/config', function($router) use ($modx) {

        // GET /api/mgr/config/page-fields/{page_key}
        // Получить конфигурацию полей с примененными переопределениями
        $router->get('/page-fields/{page_key}', function($params) use ($modx) {
            $pageKey = $params['page_key'] ?? '';

            if (empty($pageKey)) {
                return Response::error('Page key is required', 400);
            }

            try {
                /** @var \MiniShop3\Services\ConfigManager $configManager */
                $configManager = $modx->services->get('config_manager');
                $config = $configManager->getPageFieldsConfig($pageKey);

                return Response::success($config);
            } catch (\Exception $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ConfigManager] ' . $e->getMessage());
                return Response::error('Failed to load config: ' . $e->getMessage(), 500);
            }
        });

        // GET /api/mgr/config/page-fields/{page_key}/all
        // Получить ВСЕ доступные поля (включая скрытые)
        $router->get('/page-fields/{page_key}/all', function($params) use ($modx) {
            $pageKey = $params['page_key'] ?? '';

            if (empty($pageKey)) {
                return Response::error('Page key is required', 400);
            }

            try {
                /** @var \MiniShop3\Services\ConfigManager $configManager */
                $configManager = $modx->services->get('config_manager');
                $fields = $configManager->getAllFields($pageKey);

                return Response::success(['fields' => $fields]);
            } catch (\Exception $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ConfigManager] ' . $e->getMessage());
                return Response::error('Failed to load fields: ' . $e->getMessage(), 500);
            }
        });

        // PUT /api/mgr/config/page-fields/{page_key}
        // Сохранить массовые переопределения полей
        $router->put('/page-fields/{page_key}', function($params) use ($modx) {
            $pageKey = $params['page_key'] ?? '';

            if (empty($pageKey)) {
                return Response::error('Page key is required', 400);
            }

            // Получаем данные из body запроса
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!isset($data['fields']) || !is_array($data['fields'])) {
                return Response::error('Fields array is required', 400);
            }

            try {
                /** @var \MiniShop3\Services\ConfigManager $configManager */
                $configManager = $modx->services->get('config_manager');
                $success = $configManager->saveFieldsConfig($pageKey, $data['fields']);

                if ($success) {
                    return Response::success([
                        'message' => 'Configuration saved successfully',
                    ]);
                } else {
                    return Response::error('Failed to save configuration', 500);
                }
            } catch (\Exception $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ConfigManager] ' . $e->getMessage());
                return Response::error('Failed to save config: ' . $e->getMessage(), 500);
            }
        });

        // DELETE /api/mgr/config/page-fields/{page_key}/{field_name}
        // Удалить переопределение для конкретного поля
        $router->delete('/page-fields/{page_key}/{field_name}', function($params) use ($modx) {
            $pageKey = $params['page_key'] ?? '';
            $fieldName = $params['field_name'] ?? '';

            if (empty($pageKey) || empty($fieldName)) {
                return Response::error('Page key and field name are required', 400);
            }

            try {
                /** @var \MiniShop3\Services\ConfigManager $configManager */
                $configManager = $modx->services->get('config_manager');
                $success = $configManager->removeFieldOverride($pageKey, $fieldName);

                if ($success) {
                    return Response::success([
                        'message' => 'Override removed successfully',
                    ]);
                } else {
                    return Response::error('Failed to remove override', 500);
                }
            } catch (\Exception $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ConfigManager] ' . $e->getMessage());
                return Response::error('Failed to remove override: ' . $e->getMessage(), 500);
            }
        });

    });

    // Группа роутов с общей авторизацией и правами
    $router->group('/products', function($router) use ($modx) {

        // GET /api/mgr/products - список товаров
        // PUT /api/mgr/products/{id} - обновление товара
        // DELETE /api/mgr/products/{id} - удаление товара
        // Здесь будут добавляться роуты для работы с товарами

    }, [
        new AuthMiddleware($modx, 'mgr'),
        new PermissionMiddleware($modx, 'msproduct_save')
    ]);

}, [
    // Middleware для всей группы /api/mgr
    new AuthMiddleware($modx, 'mgr')
]);

// ============================================
// API для фронтенда (Web)
// ============================================
$router->group('/api/web', function($router) use ($modx) {

    // Публичные роуты (без авторизации)
    $router->get('/catalog/products', function($params) use ($modx) {
        // Получение списка товаров для каталога
        return Response::success([
            'products' => [],
            'total' => 0
        ]);
    });

    // Защищённые роуты (требуется авторизация пользователя)
    $router->group('/cart', function($router) use ($modx) {

        // POST /api/web/cart/add - добавить в корзину
        // GET /api/web/cart - получить корзину
        // DELETE /api/web/cart/{id} - удалить из корзины

    }, [
        new AuthMiddleware($modx, 'web')
    ]);

});
