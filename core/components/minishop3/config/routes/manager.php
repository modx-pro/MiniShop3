<?php
/**
 * Manager API Routes для MiniShop3
 *
 * Маршруты для административного API (connector.php).
 * Используются в MODX manager для работы ExtJS/Vue интерфейсов.
 *
 * Этот файл является частью компонента и обновляется вместе с ним.
 *
 * ❌ НЕ редактируйте этот файл напрямую!
 * ✅ Для своих роутов используйте: core/config/ms3_routes_manager.custom.php
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
            'timestamp' => time(),
            'api' => 'manager'
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

    // Группа роутов для управления дополнительными полями (Extra Fields)
    // Только для Administrator (permission: ms3_extra_fields_manage)
    $router->group('/extra-fields', function($router) use ($modx) {

        // GET /api/mgr/extra-fields - получить список всех дополнительных полей
        $router->get('', function($params) use ($modx) {
            try {
                /** @var \MiniShop3\Services\ExtraFieldsService $service */
                $service = new \MiniShop3\Services\ExtraFieldsService($modx);

                $class = $_GET['class'] ?? null;
                $criteria = $class ? ['class' => $class] : [];

                $fields = $service->getFields($criteria);

                return Response::success([
                    'fields' => $fields,
                    'total' => count($fields)
                ]);
            } catch (\Exception $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ExtraFields API] ' . $e->getMessage());
                return Response::error('Failed to load extra fields: ' . $e->getMessage(), 500);
            }
        });

        // GET /api/mgr/extra-fields/{id} - получить информацию о поле
        $router->get('/{id}', function($params) use ($modx) {
            $id = (int)($params['id'] ?? 0);

            if (!$id) {
                return Response::error('Field ID is required', 400);
            }

            $field = $modx->getObject(\MiniShop3\Model\msExtraField::class, $id);

            if (!$field) {
                return Response::error('Field not found', 404);
            }

            $data = $field->toArray();

            // Проверяем существование колонки
            $extraFieldsUtil = new \MiniShop3\Utils\ExtraFields($modx);
            $data['column_exists'] = $extraFieldsUtil->columnExists($field->get('class'), $field->get('key'));

            return Response::success(['field' => $data]);
        });

        // POST /api/mgr/extra-fields - создать новое дополнительное поле
        $router->post('', function($params) use ($modx) {
            try {
                $data = json_decode(file_get_contents('php://input'), true);

                if (empty($data)) {
                    return Response::error('Request body is empty', 400);
                }

                /** @var \MiniShop3\Services\ExtraFieldsService $service */
                $service = new \MiniShop3\Services\ExtraFieldsService($modx);

                $result = $service->createField($data);

                if (!$result['success']) {
                    return Response::error($result['message'], 400);
                }

                return Response::success([
                    'message' => $result['message'],
                    'field' => $result['data'],
                    'migration' => $result['migration']
                ]);
            } catch (\Exception $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ExtraFields API] ' . $e->getMessage());
                return Response::error('Failed to create field: ' . $e->getMessage(), 500);
            }
        });

        // PUT /api/mgr/extra-fields/{id} - обновить дополнительное поле (только метаданные)
        $router->put('/{id}', function($params) use ($modx) {
            $id = (int)($params['id'] ?? 0);

            if (!$id) {
                return Response::error('Field ID is required', 400);
            }

            try {
                $data = json_decode(file_get_contents('php://input'), true);

                if (empty($data)) {
                    return Response::error('Request body is empty', 400);
                }

                /** @var \MiniShop3\Services\ExtraFieldsService $service */
                $service = new \MiniShop3\Services\ExtraFieldsService($modx);

                $result = $service->updateField($id, $data);

                if (!$result['success']) {
                    return Response::error($result['message'], 400);
                }

                return Response::success([
                    'message' => $result['message'],
                    'field' => $result['data']
                ]);
            } catch (\Exception $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ExtraFields API] ' . $e->getMessage());
                return Response::error('Failed to update field: ' . $e->getMessage(), 500);
            }
        });

        // DELETE /api/mgr/extra-fields/{id} - удалить дополнительное поле
        $router->delete('/{id}', function($params) use ($modx) {
            $id = (int)($params['id'] ?? 0);

            if (!$id) {
                return Response::error('Field ID is required', 400);
            }

            try {
                /** @var \MiniShop3\Services\ExtraFieldsService $service */
                $service = new \MiniShop3\Services\ExtraFieldsService($modx);

                $result = $service->deleteField($id);

                if (!$result['success']) {
                    return Response::error($result['message'], 400);
                }

                return Response::success([
                    'message' => $result['message'],
                    'migration' => $result['migration']
                ]);
            } catch (\Exception $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ExtraFields API] ' . $e->getMessage());
                return Response::error('Failed to delete field: ' . $e->getMessage(), 500);
            }
        });

    }, [
        new PermissionMiddleware($modx, 'mssetting_save') // TODO: заменить на ms3_extra_fields_manage после создания permission
    ]);

    // ============================================
    // CUSTOMERS API (Клиенты)
    // ============================================
    $router->group('/customers', function($router) use ($modx) {

        // GET /api/mgr/customers - получить список клиентов с пагинацией и поиском
        $router->get('', function($params) use ($modx) {
            // Объединяем параметры из URL path и query string
            $allParams = array_merge($_GET, $params);

            $controller = new \MiniShop3\Controllers\Api\Manager\CustomersController($modx);
            return $controller->getList($allParams);
        });

        // GET /api/mgr/customers/{id} - получить конкретного клиента
        $router->get('/{id}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Manager\CustomersController($modx);
            return $controller->get($params);
        });

        // PUT /api/mgr/customers/{id} - обновить клиента
        $router->put('/{id}', function($params) use ($modx) {
            // Читаем JSON body
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?: [];
            $data['id'] = $params['id'] ?? null;

            $controller = new \MiniShop3\Controllers\Api\Manager\CustomersController($modx);
            return $controller->update($data);
        });

        // DELETE /api/mgr/customers/{id} - удалить клиента
        $router->delete('/{id}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Manager\CustomersController($modx);
            return $controller->delete($params);
        });

        // ============================================
        // ADDRESSES (Адреса клиента)
        // ============================================

        // GET /api/mgr/customers/{id}/addresses - получить адреса клиента
        $router->get('/{id}/addresses', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Manager\CustomerAddressesController($modx);
            return $controller->getList($params);
        });

        // POST /api/mgr/customers/{id}/addresses - создать новый адрес
        $router->post('/{id}/addresses', function($params) use ($modx) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?: [];
            $data['customer_id'] = $params['id'] ?? null;

            $controller = new \MiniShop3\Controllers\Api\Manager\CustomerAddressesController($modx);
            return $controller->create($data);
        });

        // PUT /api/mgr/customers/{id}/addresses/{address_id} - обновить адрес
        $router->put('/{id}/addresses/{address_id}', function($params) use ($modx) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?: [];
            $data['customer_id'] = $params['id'] ?? null;
            $data['id'] = $params['address_id'] ?? null;

            $controller = new \MiniShop3\Controllers\Api\Manager\CustomerAddressesController($modx);
            return $controller->update($data);
        });

        // DELETE /api/mgr/customers/{id}/addresses/{address_id} - удалить адрес
        $router->delete('/{id}/addresses/{address_id}', function($params) use ($modx) {
            $params['address_id'] = $params['address_id'] ?? null;

            $controller = new \MiniShop3\Controllers\Api\Manager\CustomerAddressesController($modx);
            return $controller->delete($params);
        });

    }, [
        new PermissionMiddleware($modx, 'view_document') // TODO: создать специфичное право ms3_customers_view
    ]);

    // ============================================
    // GRID CONFIGURATION API (Конфигурация гридов)
    // ============================================
    $router->group('/grid-config', function($router) use ($modx) {

        // GET /api/mgr/grid-config/{grid_key} - получить конфигурацию грида
        $router->get('/{grid_key}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Manager\GridConfigController($modx);
            return $controller->getConfig($params);
        });

        // PUT /api/mgr/grid-config/{grid_key} - сохранить конфигурацию грида
        $router->put('/{grid_key}', function($params) use ($modx) {
            // Читаем JSON body
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?: [];
            $data['grid_key'] = $params['grid_key'] ?? null;

            $controller = new \MiniShop3\Controllers\Api\Manager\GridConfigController($modx);
            return $controller->saveConfig($data);
        });

        // POST /api/mgr/grid-config/{grid_key}/field - добавить новое поле
        $router->post('/{grid_key}/field', function($params) use ($modx) {
            // Читаем JSON body
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?: [];
            $data['grid_key'] = $params['grid_key'] ?? null;

            $controller = new \MiniShop3\Controllers\Api\Manager\GridConfigController($modx);
            return $controller->addField($data);
        });

        // PUT /api/mgr/grid-config/{grid_key}/field/{field_name} - обновить поле
        $router->put('/{grid_key}/field/{field_name}', function($params) use ($modx) {
            // Читаем JSON body
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?: [];
            $data['grid_key'] = $params['grid_key'] ?? null;
            $data['field_name'] = $params['field_name'] ?? null;

            $controller = new \MiniShop3\Controllers\Api\Manager\GridConfigController($modx);
            return $controller->updateField($data);
        });

        // DELETE /api/mgr/grid-config/{grid_key}/{field_name} - удалить поле
        $router->delete('/{grid_key}/{field_name}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Manager\GridConfigController($modx);
            return $controller->deleteField($params);
        });

    }, [
        new PermissionMiddleware($modx, 'view_document') // TODO: создать специфичное право ms3_grid_config_manage
    ]);

    // ============================================
    // NOTIFICATIONS API (Центр уведомлений)
    // ============================================
    $router->group('/notifications', function($router) use ($modx) {

        // GET /api/mgr/notifications/references - получить справочники для формы
        $router->get('/references', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Manager\NotificationsController($modx);
            return $controller->getReferences();
        });

        // GET /api/mgr/notifications - получить список настроек уведомлений
        $router->get('', function($params) use ($modx) {
            $allParams = array_merge($_GET, $params);

            $controller = new \MiniShop3\Controllers\Api\Manager\NotificationsController($modx);
            return $controller->getList($allParams);
        });

        // GET /api/mgr/notifications/{id} - получить конкретную настройку
        $router->get('/{id}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Manager\NotificationsController($modx);
            return $controller->get($params);
        });

        // POST /api/mgr/notifications - создать новую настройку
        $router->post('', function($params) use ($modx) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?: [];

            $controller = new \MiniShop3\Controllers\Api\Manager\NotificationsController($modx);
            return $controller->create($data);
        });

        // PUT /api/mgr/notifications/{id} - обновить настройку
        $router->put('/{id}', function($params) use ($modx) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?: [];
            $data['id'] = $params['id'] ?? null;

            $controller = new \MiniShop3\Controllers\Api\Manager\NotificationsController($modx);
            return $controller->update($data);
        });

        // DELETE /api/mgr/notifications/{id} - удалить настройку
        $router->delete('/{id}', function($params) use ($modx) {
            $controller = new \MiniShop3\Controllers\Api\Manager\NotificationsController($modx);
            return $controller->delete($params);
        });

    }, [
        new PermissionMiddleware($modx, 'mssetting_save') // Требуется право на управление настройками
    ]);

}, [
    // Middleware для всей группы /api/mgr
    new AuthMiddleware($modx, 'mgr')
]);
