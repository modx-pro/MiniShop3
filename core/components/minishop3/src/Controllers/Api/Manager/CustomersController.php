<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCustomer;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API контроллер для управления клиентами (Manager API)
 *
 * Обрабатывает CRUD операции для клиентов в админке.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class CustomersController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Получить список клиентов с пагинацией и поиском
     * GET /api/mgr/customers
     *
     * @param array $params URL параметры (start, limit, query)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 20);
        $query = trim($params['query'] ?? '');

        // Загружаем конфигурацию грида для получения relation и computed полей
        $gridConfig = $this->modx->services->get('ms3_grid_config');
        $gridFields = $gridConfig ? $gridConfig->getGridConfig('customers') : [];

        // Находим relation и computed поля
        $relationFields = $this->extractRelationFields($gridFields);
        $computedFields = $this->extractComputedFields($gridFields);

        // Базовый критерий
        $criteria = [];

        // Поиск по имени, фамилии, email, телефону
        if (!empty($query)) {
            $criteria[] = [
                'first_name:LIKE' => "%{$query}%",
                'OR:last_name:LIKE' => "%{$query}%",
                'OR:email:LIKE' => "%{$query}%",
                'OR:phone:LIKE' => "%{$query}%",
            ];
        }

        // Фильтрация по колонкам (filter_email, filter_phone и т.д.)
        foreach ($params as $key => $value) {
            if (strpos($key, 'filter_') === 0 && !empty($value)) {
                $fieldName = substr($key, 7); // Убираем префикс "filter_"

                // Для поля active используем точное совпадение
                if ($fieldName === 'active') {
                    $criteria['is_active'] = (int)$value;
                } else {
                    // Для остальных полей используем LIKE
                    $criteria[$fieldName . ':LIKE'] = "%{$value}%";
                }
            }
        }

        // Получаем общее количество
        $total = $this->modx->getCount(msCustomer::class, $criteria);

        // Получаем записи с пагинацией
        $customers = $this->modx->getIterator(msCustomer::class, $criteria, [
            'limit' => $limit,
            'offset' => $start,
            'sortby' => 'id',
            'sortdir' => 'DESC'
        ]);

        $results = [];

        // Если есть relation или computed поля, обрабатываем их
        if (!empty($relationFields) || !empty($computedFields)) {
            $customerIds = [];
            $customerObjects = [];

            foreach ($customers as $customer) {
                $customerIds[] = $customer->get('id');
                $customerObjects[$customer->get('id')] = $customer;
            }

            // Получаем агрегированные данные для всех relation полей
            $relationData = [];
            if (!empty($relationFields)) {
                $relationData = $this->fetchRelationData($customerIds, $relationFields);
            }

            // Форматируем результаты с добавлением relation и computed данных
            foreach ($customerObjects as $customerId => $customer) {
                $formatted = $this->formatCustomer($customer);

                // Добавляем данные из relation полей
                foreach ($relationFields as $fieldName => $config) {
                    $formatted[$fieldName] = $relationData[$customerId][$fieldName] ?? 0;
                }

                // Вычисляем computed поля
                foreach ($computedFields as $fieldName => $config) {
                    $formatted[$fieldName] = $this->computeField($formatted, $config);
                }

                $results[] = $formatted;
            }
        } else {
            // Без relation и computed полей - стандартная обработка
            foreach ($customers as $customer) {
                $results[] = $this->formatCustomer($customer);
            }
        }

        return Response::success([
            'results' => $results,
            'total' => $total
        ])->getData();
    }

    /**
     * Получить конкретного клиента
     * GET /api/mgr/customers/{id}
     *
     * @param array $params URL параметры (id)
     * @return array Response
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Customer ID is required', 400)->getData();
        }

        $customer = $this->modx->getObject(msCustomer::class, $id);

        if (!$customer) {
            return Response::error('Customer not found', 404)->getData();
        }

        return Response::success($this->formatCustomer($customer))->getData();
    }

    /**
     * Обновить клиента
     * PUT /api/mgr/customers/{id}
     *
     * @param array $data Данные для обновления
     * @return array Response
     */
    public function update(array $data = []): array
    {
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            return Response::error('Customer ID is required', 400)->getData();
        }

        $customer = $this->modx->getObject(msCustomer::class, $id);

        if (!$customer) {
            return Response::error('Customer not found', 404)->getData();
        }

        // Обновляемые поля
        $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'is_active', 'is_blocked'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $customer->set($field, $data[$field]);
            }
        }

        // Обработка пароля (отдельно, т.к. требует хеширования)
        if (!empty($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $customer->set('password', $hashedPassword);
        }

        if (!$customer->save()) {
            return Response::error('Failed to save customer', 500)->getData();
        }

        return Response::success($this->formatCustomer($customer), 'Customer updated successfully')->getData();
    }

    /**
     * Удалить клиента
     * DELETE /api/mgr/customers/{id}
     *
     * @param array $params URL параметры (id)
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Customer ID is required', 400)->getData();
        }

        $customer = $this->modx->getObject(msCustomer::class, $id);

        if (!$customer) {
            return Response::error('Customer not found', 404)->getData();
        }

        // Удаляем связанные записи
        // Адреса
        $addresses = $this->modx->getIterator(\MiniShop3\Model\msCustomerAddress::class, ['customer_id' => $id]);
        foreach ($addresses as $address) {
            $address->remove();
        }

        // Токены
        $tokens = $this->modx->getIterator(\MiniShop3\Model\msCustomerToken::class, ['customer_id' => $id]);
        foreach ($tokens as $token) {
            $token->remove();
        }

        // Удаляем самого клиента
        if (!$customer->remove()) {
            return Response::error('Failed to delete customer', 500)->getData();
        }

        return Response::success([], 'Customer deleted successfully')->getData();
    }

    /**
     * Форматировать объект клиента для API ответа
     *
     * @param msCustomer $customer
     * @return array
     */
    protected function formatCustomer(msCustomer $customer): array
    {
        return [
            'id' => $customer->get('id'),
            'first_name' => $customer->get('first_name'),
            'last_name' => $customer->get('last_name'),
            'email' => $customer->get('email'),
            'phone' => $customer->get('phone'),
            'is_active' => (bool)$customer->get('is_active'),
            'is_blocked' => (bool)$customer->get('is_blocked'),
            'email_verified_at' => $customer->get('email_verified_at'),
            'created_at' => $customer->get('created_at'),
            'updated_at' => $customer->get('updated_at'),
            'last_login_at' => $customer->get('last_login_at'),
            'orders_count' => (int)$customer->get('orders_count'),
            'total_spent' => (float)$customer->get('total_spent'),
        ];
    }

    /**
     * Извлечь relation поля из конфигурации грида
     *
     * @param array $gridFields Конфигурация полей грида
     * @return array Массив relation полей ['field_name' => config]
     */
    protected function extractRelationFields(array $gridFields): array
    {
        $relationFields = [];

        foreach ($gridFields as $field) {
            // Проверяем что это relation поле
            if (isset($field['type']) && $field['type'] === 'relation') {
                $fieldName = $field['name'] ?? null;
                $relation = $field['relation'] ?? [];

                if ($fieldName && !empty($relation)) {
                    $relationFields[$fieldName] = $relation;
                }
            }
        }

        return $relationFields;
    }

    /**
     * Извлечь computed поля из конфигурации грида
     *
     * @param array $gridFields Конфигурация полей грида
     * @return array Массив computed полей ['field_name' => config]
     */
    protected function extractComputedFields(array $gridFields): array
    {
        $computedFields = [];

        foreach ($gridFields as $field) {
            // Проверяем что это computed поле
            if (isset($field['type']) && $field['type'] === 'computed') {
                $fieldName = $field['name'] ?? null;
                $computed = $field['computed'] ?? [];

                if ($fieldName && !empty($computed)) {
                    $computedFields[$fieldName] = $computed;
                }
            }
        }

        return $computedFields;
    }

    /**
     * Получить агрегированные данные для relation полей
     *
     * @param array $customerIds Массив ID клиентов
     * @param array $relationFields Конфигурация relation полей
     * @return array Массив [customer_id => [field_name => value]]
     */
    protected function fetchRelationData(array $customerIds, array $relationFields): array
    {
        if (empty($customerIds) || empty($relationFields)) {
            return [];
        }

        $result = [];

        // Инициализируем результат нулями для всех клиентов и полей
        foreach ($customerIds as $customerId) {
            $result[$customerId] = [];
            foreach ($relationFields as $fieldName => $config) {
                $result[$customerId][$fieldName] = 0;
            }
        }

        // Получаем имя таблицы customers
        $customersTable = $this->modx->getTableName(msCustomer::class);

        // Для каждого relation поля выполняем отдельный запрос
        foreach ($relationFields as $fieldName => $config) {
            $relationTable = $config['resolvedTableName'] ?? $config['table'] ?? null;
            $foreignKey = $config['foreignKey'] ?? null;
            $displayField = $config['displayField'] ?? null;
            $aggregation = $config['aggregation'] ?? null;

            if (!$relationTable || !$foreignKey || !$displayField) {
                continue;
            }

            // Строим SELECT в зависимости от агрегации
            if ($aggregation) {
                $selectExpr = "{$aggregation}({$relationTable}.{$displayField})";
            } else {
                $selectExpr = "{$relationTable}.{$displayField}";
            }

            // Строим SQL запрос
            $sql = "
                SELECT
                    {$customersTable}.id as customer_id,
                    {$selectExpr} as field_value
                FROM {$customersTable}
                LEFT JOIN {$relationTable} ON {$relationTable}.{$foreignKey} = {$customersTable}.id
                WHERE {$customersTable}.id IN (" . implode(',', $customerIds) . ")
                GROUP BY {$customersTable}.id
            ";

            $stmt = $this->modx->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Заполняем результат
            foreach ($rows as $row) {
                $customerId = (int)$row['customer_id'];
                $value = $row['field_value'];

                // Приводим к нужному типу в зависимости от агрегации
                if ($aggregation === 'COUNT') {
                    $value = (int)$value;
                } elseif (in_array($aggregation, ['SUM', 'AVG'])) {
                    $value = (float)$value;
                }

                $result[$customerId][$fieldName] = $value ?? 0;
            }
        }

        return $result;
    }

    /**
     * Вычислить значение computed поля
     *
     * @param array $row Данные строки (клиента)
     * @param array $config Конфигурация computed поля
     * @return mixed Вычисленное значение
     */
    protected function computeField(array $row, array $config)
    {
        $className = $config['className'] ?? null;

        if (!$className) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[CustomersController] Computed field className not specified');
            return null;
        }

        // Проверяем существование класса
        if (!class_exists($className)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[CustomersController] Computed class not found: {$className}");
            return null;
        }

        // Проверяем реализацию интерфейса
        $interfaces = class_implements($className);
        if (!isset($interfaces['MiniShop3\\Interfaces\\ComputedFieldInterface'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[CustomersController] Class {$className} must implement ComputedFieldInterface");
            return null;
        }

        try {
            // Создаём экземпляр и вызываем compute()
            $instance = new $className();
            return $instance->compute($row);
        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[CustomersController] Error computing field with {$className}: {$e->getMessage()}");
            return null;
        }
    }
}
