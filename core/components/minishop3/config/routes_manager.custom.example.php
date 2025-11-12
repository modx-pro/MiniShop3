<?php
/**
 * ПОЛЬЗОВАТЕЛЬСКИЕ Manager API Routes для MiniShop3
 *
 * ✅ Этот файл НЕ перезаписывается при обновлении компонента!
 * ✅ Здесь можно безопасно добавлять свои роуты для админки
 *
 * При установке компонента этот файл копируется в:
 * core/config/ms3_routes_manager.custom.php
 *
 * Загружается ПОСЛЕ системных роутов Manager API, поэтому можно переопределять.
 *
 * Доступ к переменным:
 * @var \MiniShop3\Router\Router $router
 * @var \MODX\Revolution\modX $modx
 */

use MiniShop3\Router\Middleware\AuthMiddleware;
use MiniShop3\Router\Middleware\PermissionMiddleware;
use MiniShop3\Router\Response;

// ============================================
// Примеры пользовательских Manager API роутов
// ============================================

// Пример 1: Простой роут для админки (требуется авторизация)
// $router->get('/api/mgr/my-custom-route', function() use ($modx) {
//     return Response::success(['message' => 'Custom Manager route works!']);
// }, [
//     new AuthMiddleware($modx, 'mgr')
// ]);

// Пример 2: Группа роутов для своего модуля в админке
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
//         // Ваша логика сохранения настроек
//         $data = json_decode(file_get_contents('php://input'), true);
//         return Response::success(['saved' => true, 'data' => $data]);
//     });
//
// }, [
//     new AuthMiddleware($modx, 'mgr'),
//     new PermissionMiddleware($modx, 'your_custom_permission')
// ]);

// Пример 3: Переопределение системного роута Manager API
// Если нужно изменить поведение системного роута - скопируйте его сюда
// $router->get('/api/mgr/health', function() use ($modx) {
//     return Response::success([
//         'status' => 'custom_ok',
//         'api' => 'manager',
//         'custom' => true
//     ]);
// });

// Пример 4: Роут с параметрами
// $router->get('/api/mgr/my-resource/{id}', function($params) use ($modx) {
//     $id = $params['id'] ?? 0;
//
//     $resource = $modx->getObject('modResource', $id);
//     if (!$resource) {
//         return Response::error('Resource not found', 404);
//     }
//
//     return Response::success(['resource' => $resource->toArray()]);
// }, [
//     new AuthMiddleware($modx, 'mgr')
// ]);
