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
 * Available variables:
 * @var \MiniShop3\Router\Router $router
 * @var \MODX\Revolution\modX $modx
 */

use MiniShop3\Router\Middleware\AuthMiddleware;
use MiniShop3\Router\Middleware\PermissionMiddleware;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;

// Example: Custom public route (no auth required)
// $router->get('/api/web/my-custom-route', function() use ($modx) {
//     return Response::success(['message' => 'Custom Web route works!']);
// });

// Example: Custom route with customer authentication
// $router->get('/api/web/customer/wishlist', function() use ($modx) {
//     // Customer must be logged in (token required)
//     $customer = $modx->user; // or get from token
//     return Response::success([
//         'wishlist' => [] // your wishlist logic here
//     ]);
// }, [
//     new AuthMiddleware($modx, 'web')
// ]);

// Example: Custom route with permission check
// $router->post('/api/web/export/products', function($params) use ($modx) {
//     $format = $params['format'] ?? 'csv';
//     // Export logic here
//     return Response::success([
//         'download_url' => '/assets/exports/products.csv'
//     ]);
// }, [
//     new AuthMiddleware($modx, 'web'),
//     new PermissionMiddleware($modx, 'ms3_export')
// ]);

// Example: Grouped routes with common middleware
// $router->group('/api/web/b2b', function($router) use ($modx) {
//
//     $router->get('/prices', function() use ($modx) {
//         return Response::success([
//             'prices' => [] // B2B price list
//         ]);
//     });
//
//     $router->post('/order', function($params) use ($modx) {
//         // B2B order creation
//         return Response::success(['order_id' => 123]);
//     });
//
// }, [
//     new AuthMiddleware($modx, 'web'),
//     new PermissionMiddleware($modx, 'ms3_b2b')
// ]);

// Example: Health check endpoint
// $router->get('/api/web/health', function() use ($modx) {
//     return Response::success([
//         'status' => 'custom_ok',
//         'api' => 'web',
//         'custom' => true
//     ]);
// });
