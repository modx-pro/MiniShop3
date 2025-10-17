<?php
/**
 * ПОЛЬЗОВАТЕЛЬСКИЕ API Routes для MiniShop3
 *
 * ✅ Этот файл НЕ перезаписывается при обновлении компонента!
 * ✅ Здесь можно безопасно добавлять свои роуты
 *
 * При установке компонента этот файл копируется в:
 * core/config/ms3_routes.custom.php
 *
 * Загружается ПОСЛЕ системных роутов, поэтому можно переопределять.
 *
 * Доступ к переменным:
 * @var \MiniShop3\Router\Router $router
 * @var \MODX\Revolution\modX $modx
 */

use MiniShop3\Router\Middleware\AuthMiddleware;
use MiniShop3\Router\Middleware\PermissionMiddleware;
use MiniShop3\Router\Response;

// ============================================
// Примеры пользовательских роутов
// ============================================

// Пример 1: Простой роут
// $router->get('/api/mgr/my-custom-route', function() use ($modx) {
//     return Response::success(['message' => 'Custom route works!']);
// }, [
//     new AuthMiddleware($modx, 'mgr')
// ]);

// Пример 2: Группа роутов для своего модуля
// $router->group('/api/mgr/my-module', function($router) use ($modx) {
//
//     $router->get('/test', function() use ($modx) {
//         return Response::success(['status' => 'ok']);
//     });
//
//     $router->post('/save', function($params) use ($modx) {
//         // Ваша логика сохранения
//         return Response::success(['saved' => true]);
//     });
//
// }, [
//     new AuthMiddleware($modx, 'mgr'),
//     new PermissionMiddleware($modx, 'your_permission')
// ]);

// Пример 3: Переопределение системного роута
// Если нужно изменить поведение системного роута - скопируйте его сюда
// $router->get('/api/mgr/health', function() use ($modx) {
//     return Response::success([
//         'status' => 'custom_ok',
//         'custom' => true
//     ]);
// });
