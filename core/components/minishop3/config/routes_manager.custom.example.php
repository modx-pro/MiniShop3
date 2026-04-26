<?php
/**
 * CUSTOM Manager API Routes for MiniShop3
 *
 * This file is NOT overwritten during component updates!
 * You can safely add your custom admin routes here.
 *
 * During component installation, this file is copied to:
 * core/config/ms3_routes_manager.custom.php
 *
 * Loaded AFTER system Manager API routes, so you can override them.
 *
 * Available variables:
 * @var \MiniShop3\Router\Router $router
 * @var \MODX\Revolution\modX $modx
 */

use MiniShop3\Router\Middleware\AuthMiddleware;
use MiniShop3\Router\Middleware\PermissionMiddleware;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
// $router->get('/api/mgr/my-custom-route', function() use ($modx) {
//     return Response::success(['message' => 'Custom Manager route works!']);
// }, [
//     new AuthMiddleware($modx, 'mgr')
// ]);
// $router->group('/api/mgr/my-module', function($router) use ($modx) {
//
//     $router->get('/dashboard', function() use ($modx) {
//         return Response::success([
//             'stats' => [
//                 'users' => 100,
//                 'orders' => 50
//             ]
//         ]);
//     });
//
//     $router->post('/settings/save', function($params) use ($modx) {
//         $data = json_decode(file_get_contents('php://input'), true);
//         return Response::success(['saved' => true, 'data' => $data]);
//     });
//
// }, [
//     new AuthMiddleware($modx, 'mgr'),
//     new PermissionMiddleware($modx, 'your_custom_permission')
// ]);
// $router->get('/api/mgr/health', function() use ($modx) {
//     return Response::success([
//         'status' => 'custom_ok',
//         'api' => 'manager',
//         'custom' => true
//     ]);
// });
// $router->get('/api/mgr/my-resource/{id}', function($params) use ($modx) {
//     $id = $params['id'] ?? 0;
//
//     $resource = $modx->getObject('modResource', $id);
//     if (!$resource) {
//         return Response::error('Resource not found', HttpStatus::NOT_FOUND);
//     }
//
//     return Response::success(['resource' => $resource->toArray()]);
// }, [
//     new AuthMiddleware($modx, 'mgr')
// ]);
