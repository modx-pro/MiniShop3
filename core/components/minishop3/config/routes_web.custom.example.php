<?php
/**
 * CUSTOM Web API Routes for MiniShop3
 *
 * This file is NOT overwritten during component updates!
 * You can safely add your custom frontend/cart/customer routes here.
 *
 * During component installation, this file is copied to:
 * core/config/ms3_routes_web.custom.php
 *
 * Loaded AFTER system Web API routes, so you can override them.
 *
 * Web API namespace is /api/v1 — Router recognizes only that prefix as a
 * storefront route (CORS preflight depends on it).
 *
 * Available variables:
 * @var \MiniShop3\Router\Router $router
 * @var \MODX\Revolution\modX $modx
 *
 * Handler signature: function (array $vars, \MODX\Revolution\modX $modx)
 */

use MiniShop3\Middleware\CorsMiddleware;
use MiniShop3\Middleware\TokenMiddleware;
use MiniShop3\Router\Response;

$corsMiddleware = new CorsMiddleware([
    'allowed_origins' => $modx->getOption('ms3_cors_allowed_origins', null, ''),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'MS3TOKEN'],
    'allow_credentials' => true,
    'max_age' => 86400,
]);

// Example: Custom public route (no auth required)
// $router->get('/api/v1/my-custom-route', function (array $vars, \MODX\Revolution\modX $modx) {
//     return Response::success(['message' => 'Custom Web route works!']);
// }, [$corsMiddleware]);

// Example: Customer route with token authentication (headless clients)
// $router->get('/api/v1/customer/wishlist', function (array $vars, \MODX\Revolution\modX $modx) {
//     // TokenMiddleware validates the ms3 bearer token and exposes the customer.
//     return Response::success(['wishlist' => []]);
// }, [$corsMiddleware, new TokenMiddleware($modx)]);

// Example: Grouped routes with common middleware
// $router->group('/api/v1/b2b', function ($router) use ($modx, $corsMiddleware) {
//     $router->get('/prices', function (array $vars, \MODX\Revolution\modX $modx) {
//         return Response::success(['prices' => []]);
//     });
//     $router->post('/order', function (array $vars, \MODX\Revolution\modX $modx) {
//         return Response::success(['order_id' => 123]);
//     });
// }, [$corsMiddleware, new TokenMiddleware($modx)]);

// Example: Health check endpoint
// $router->get('/api/v1/health', function (array $vars, \MODX\Revolution\modX $modx) {
//     return Response::success(['status' => 'custom_ok', 'api' => 'web', 'custom' => true]);
// }, [$corsMiddleware]);
