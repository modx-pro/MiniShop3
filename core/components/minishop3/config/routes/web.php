<?php
/**
 * WEB API Routes для MiniShop3
 *
 * Маршруты для фронтенд API (корзина, заказы, клиенты, каталог).
 * Используются в новом entry point: assets/components/minishop3/api.php
 *
 * Отличия от Manager API (/api/mgr/):
 * - Использует токенную авторизацию вместо MODX сессий
 * - Поддержка CORS для headless frontend
 * - Rate limiting для защиты от злоупотреблений
 * - Публичные endpoints для каталога (без авторизации)
 *
 * Этот файл является частью компонента и обновляется вместе с ним.
 *
 * ❌ НЕ редактируйте этот файл напрямую!
 * ✅ Для своих роутов используйте: core/config/ms3_routes_web.custom.php
 *
 * Пользовательские роуты загружаются ПОСЛЕ системных и могут их переопределять.
 *
 * Доступ к переменным:
 * @var \MiniShop3\Router\Router $router
 * @var \MODX\Revolution\modX $modx
 *
 * @version 1.0.0
 */

use MiniShop3\Router\Response;
use MiniShop3\Middleware\TokenMiddleware;
use MiniShop3\Middleware\CorsMiddleware;
use MiniShop3\Middleware\RateLimitMiddleware;

// Инициализация Middleware
$tokenMiddleware = new TokenMiddleware($modx);
$corsMiddleware = new CorsMiddleware([
    'allowed_origins' => $modx->getOption('ms3_cors_allowed_origins', null, ['*']),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'MS3TOKEN'],
    'allow_credentials' => true,
    'max_age' => 86400
]);
$rateLimitMiddleware = new RateLimitMiddleware(
    $modx->getOption('ms3_rate_limit_max_attempts', null, 60),
    $modx->getOption('ms3_rate_limit_decay_seconds', null, 60)
);

// ============================================
// Web API группа (v1)
// ============================================
$router->group('/api/v1', function($router) use ($modx, $tokenMiddleware) {

    // ============================================
    // CART API (Корзина)
    // ============================================
    $router->group('/cart', function($router) use ($modx) {

        // POST /api/v1/cart/add - Добавить товар в корзину
        $router->post('/add', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Web\CartController($modx);
            return $controller->add($params);
        });

        // POST /api/v1/cart/remove - Удалить товар из корзины
        $router->post('/remove', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Web\CartController($modx);
            return $controller->remove($params);
        });

        // POST /api/v1/cart/change - Изменить количество товара
        $router->post('/change', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Web\CartController($modx);
            return $controller->change($params);
        });

        // GET /api/v1/cart/get - Получить содержимое корзины
        $router->get('/get', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Web\CartController($modx);
            return $controller->get($params);
        });

        // POST /api/v1/cart/clean - Очистить корзину
        $router->post('/clean', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Web\CartController($modx);
            return $controller->clean($params);
        });

    }, [$tokenMiddleware]); // TokenMiddleware проверяет токен для всех операций корзины

    // ============================================
    // ORDER API (Заказы)
    // ============================================
    $router->group('/order', function($router) use ($modx) {

        // POST /api/v1/order/submit - Оформить заказ
        $router->post('/submit', function($params) use ($modx) {
            // TODO: Реализовать в Controllers\Api\Web\OrderController
            return Response::success(['message' => 'Order submit endpoint - not implemented yet']);
        });

        // POST /api/v1/order/clean - Очистить данные заказа
        $router->post('/clean', function($params) use ($modx) {
            // TODO: Реализовать в Controllers\Api\Web\OrderController
            return Response::success(['message' => 'Order clean endpoint - not implemented yet']);
        });

        // GET /api/v1/order/get - Получить данные текущего заказа
        $router->get('/get', function($params) use ($modx) {
            // TODO: Реализовать в Controllers\Api\Web\OrderController
            return Response::success(['message' => 'Order get endpoint - not implemented yet']);
        });

    }, [$tokenMiddleware]);

    // ============================================
    // CUSTOMER API (Покупатели)
    // ============================================
    $router->group('/customer', function($router) use ($modx) {

        // GET /api/v1/customer/token/get - Получить токен покупателя (публичный endpoint)
        $router->get('/token/get', function($params) use ($modx) {
            /** @var \MiniShop3\MiniShop3 $ms3 */
            $ms3 = $modx->services->get('ms3');
            if (!$ms3) {
                return Response::error('MiniShop3 not initialized', 500);
            }

            $ms3->initialize();
            $response = $ms3->customer->generateToken();

            // Преобразуем массив в Response
            if ($response['success']) {
                return Response::success($response['data'], $response['message'] ?? '');
            } else {
                return Response::error($response['message'] ?? 'Token generation failed', $response['code'] ?? 500);
            }
        });

        // POST /api/v1/customer/token/refresh - Обновить токен покупателя
        $router->post('/token/refresh', function($params) use ($modx) {
            // TODO: Реализовать в Controllers\Api\Web\CustomerController
            return Response::success(['message' => 'Customer token/refresh endpoint - not implemented yet']);
        });

    }); // Customer endpoints публичные (не требуют токена)

    // ============================================
    // PRODUCT API (Каталог товаров) - публичные endpoints
    // ============================================
    $router->group('/product', function($router) use ($modx) {

        // GET /api/v1/product/get/{id} - Получить информацию о товаре
        $router->get('/get/{id}', function($params) use ($modx) {
            // TODO: Реализовать в Controllers\Api\Web\ProductController
            return Response::success(['message' => 'Product get endpoint - not implemented yet', 'id' => $params['id'] ?? null]);
        });

        // GET /api/v1/product/list - Получить список товаров (для каталога)
        $router->get('/list', function($params) use ($modx) {
            // TODO: Реализовать в Controllers\Api\Web\ProductController
            return Response::success(['message' => 'Product list endpoint - not implemented yet']);
        });

    }); // Product endpoints публичные

    // ============================================
    // HEALTH CHECK (для мониторинга)
    // ============================================
    $router->get('/health', function() use ($modx) {
        return Response::success([
            'status' => 'ok',
            'version' => $modx->getOption('ms3_version', null, '1.0.0'),
            'timestamp' => time(),
            'api' => 'web'
        ]);
    });

}, [$corsMiddleware, $rateLimitMiddleware]); // CORS и Rate Limit для всех Web API endpoints
