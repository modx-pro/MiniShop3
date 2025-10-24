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
            $controller = new \MiniShop3\Controllers\Api\ConfigController($modx);
            return $controller->getPageFields($params);
        });

        // GET /api/mgr/config/page-fields/{page_key}/all
        // Получить ВСЕ доступные поля (включая скрытые) из модели
        $router->get('/page-fields/{page_key}/all', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ConfigController($modx);
            return $controller->getAllPageFields($params);
        });

        // PUT /api/mgr/config/page-fields/{page_key}
        // Сохранить массовые переопределения полей
        $router->put('/page-fields/{page_key}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ConfigController($modx);
            return $controller->updatePageFields($params);
        });

        // DELETE /api/mgr/config/page-fields/{page_key}/{field_name}
        // Удалить переопределение для конкретного поля
        $router->delete('/page-fields/{page_key}/{field_name}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ConfigController($modx);
            return $controller->deleteFieldOverride($params);
        });

        // GET /api/mgr/config/sections/{page_key}
        // Получить секции страницы с переводами из лексикона
        $router->get('/sections/{page_key}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ConfigController($modx);
            return $controller->getSections($params);
        });

        // PUT /api/mgr/config/sections/{page_key}
        // Сохранить секции (порядок, видимость)
        $router->put('/sections/{page_key}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ConfigController($modx);
            return $controller->updateSections($params);
        });

        // DELETE /api/mgr/config/sections/{page_key}/{section_key}
        // Удалить секцию (только кастомные)
        $router->delete('/sections/{page_key}/{section_key}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ConfigController($modx);
            return $controller->deleteSection($params);
        });

    });

    // Группа роутов для работы с моделями
    $router->group('/models', function($router) use ($modx) {

        // GET /api/mgr/models/{alias}/fields
        // Получить все поля модели (сырые, без конфигурации)
        $router->get('/{alias}/fields', function($params) use ($modx) {
            $alias = $params['alias'] ?? '';

            if (empty($alias)) {
                return Response::error('Model alias is required', 400);
            }

            try {
                /** @var \MiniShop3\Services\FieldConfigManager $fieldConfigManager */
                $fieldConfigManager = $modx->services->get('ms3_field_config_manager');

                $modelClass = $fieldConfigManager->getModelClassByAlias($alias);

                if (!$modelClass) {
                    return Response::error("Model alias not found: {$alias}", 404);
                }

                $fields = $fieldConfigManager->getModelFields($modelClass);

                return Response::success([
                    'model_alias' => $alias,
                    'model_class' => $modelClass,
                    'fields' => $fields,
                ]);
            } catch (\Exception $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[FieldConfigManager] ' . $e->getMessage());
                return Response::error('Failed to load model fields: ' . $e->getMessage(), 500);
            }
        });

    });

    // Группа роутов для работы с данными товара (msProductData)
    $router->group('/product-data', function($router) use ($modx) {

        // GET /api/mgr/product-data/{id} - получение данных товара
        $router->get('/{id}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ProductDataController($modx);
            return $controller->get($params);
        });

        // PUT /api/mgr/product-data/{id} - обновление данных товара
        $router->put('/{id}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ProductDataController($modx);
            return $controller->update($params);
        });

    }, [
        new AuthMiddleware($modx, 'mgr'),
        new PermissionMiddleware($modx, 'msproduct_save')
    ]);

    // Группа роутов для справочников (vendors, categories и т.д.)
    $router->group('/references', function($router) use ($modx) {

        // GET /api/mgr/references/vendors - получить список производителей
        $router->get('/vendors', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ReferencesController($modx);
            return $controller->getVendors($params);
        });

        // GET /api/mgr/references/autocomplete - получить автодополнение для поля
        $router->get('/autocomplete', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ReferencesController($modx);
            return $controller->getAutocomplete($params);
        });

        // GET /api/mgr/references/options - получить опции товара (для chips/multiselect)
        $router->get('/options', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\ReferencesController($modx);
            return $controller->getOptions($params);
        });

    });

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
