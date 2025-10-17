<?php
/**
 * СИСТЕМНЫЕ API Routes для MiniShop3
 *
 * ⚠️ ВНИМАНИЕ: Этот файл копируется в core/config/ms3_routes.php
 *              и ПЕРЕЗАПИСЫВАЕТСЯ при каждом обновлении компонента!
 *
 * ❌ НЕ редактируйте core/config/ms3_routes.php напрямую!
 * ✅ Для своих роутов используйте: core/config/ms3_routes.custom.php
 *
 * При обновлении компонента:
 * - core/config/ms3_routes.php - ПЕРЕЗАПИСЫВАЕТСЯ (новые системные роуты)
 * - core/config/ms3_routes.custom.php - НЕ ТРОГАЕТСЯ (ваши роуты в безопасности)
 *
 * Если нужно изменить/отключить системный роут:
 * - Скопируйте его в ms3_routes.custom.php
 * - Измените/переопределите там
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
