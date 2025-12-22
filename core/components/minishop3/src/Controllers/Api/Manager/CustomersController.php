<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCustomer;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API controller for customer management (Manager API)
 *
 * Handles CRUD operations for customers in admin panel.
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
     * Get list of customers with pagination and search
     * GET /api/mgr/customers
     *
     * @param array $params URL parameters (start, limit, query)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 20);
        $query = trim($params['query'] ?? '');

        $gridConfig = $this->modx->services->get('ms3_grid_config');
        $gridFields = $gridConfig ? $gridConfig->getGridConfig('customers') : [];

        $relationFields = $this->extractRelationFields($gridFields);
        $computedFields = $this->extractComputedFields($gridFields);

        $criteria = [];

        if (!empty($query)) {
            $criteria[] = [
                'first_name:LIKE' => "%{$query}%",
                'OR:last_name:LIKE' => "%{$query}%",
                'OR:email:LIKE' => "%{$query}%",
                'OR:phone:LIKE' => "%{$query}%",
            ];
        }

        foreach ($params as $key => $value) {
            if (strpos($key, 'filter_') === 0 && !empty($value)) {
                $fieldName = substr($key, 7);

                if ($fieldName === 'active') {
                    $criteria['is_active'] = (int)$value;
                } else {
                    $criteria[$fieldName . ':LIKE'] = "%{$value}%";
                }
            }
        }

        $total = $this->modx->getCount(msCustomer::class, $criteria);

        $customers = $this->modx->getIterator(msCustomer::class, $criteria, [
            'limit' => $limit,
            'offset' => $start,
            'sortby' => 'id',
            'sortdir' => 'DESC'
        ]);

        $results = [];

        if (!empty($relationFields) || !empty($computedFields)) {
            $customerIds = [];
            $customerObjects = [];

            foreach ($customers as $customer) {
                $customerIds[] = $customer->get('id');
                $customerObjects[$customer->get('id')] = $customer;
            }

            $relationData = [];
            if (!empty($relationFields)) {
                $relationData = $this->fetchRelationData($customerIds, $relationFields);
            }

            foreach ($customerObjects as $customerId => $customer) {
                $formatted = $this->formatCustomer($customer);

                foreach ($relationFields as $fieldName => $config) {
                    $formatted[$fieldName] = $relationData[$customerId][$fieldName] ?? 0;
                }

                foreach ($computedFields as $fieldName => $config) {
                    $formatted[$fieldName] = $this->computeField($formatted, $config);
                }

                $results[] = $formatted;
            }
        } else {
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
     * Get specific customer
     * GET /api/mgr/customers/{id}
     *
     * @param array $params URL parameters (id)
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
     * Update customer
     * PUT /api/mgr/customers/{id}
     *
     * @param array $data Update data
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

        $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'is_active', 'is_blocked'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $customer->set($field, $data[$field]);
            }
        }

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
     * Delete customer
     * DELETE /api/mgr/customers/{id}
     *
     * @param array $params URL parameters (id)
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

        $addresses = $this->modx->getIterator(\MiniShop3\Model\msCustomerAddress::class, ['customer_id' => $id]);
        foreach ($addresses as $address) {
            $address->remove();
        }

        $tokens = $this->modx->getIterator(\MiniShop3\Model\msCustomerToken::class, ['customer_id' => $id]);
        foreach ($tokens as $token) {
            $token->remove();
        }

        if (!$customer->remove()) {
            return Response::error('Failed to delete customer', 500)->getData();
        }

        return Response::success([], 'Customer deleted successfully')->getData();
    }

    /**
     * Bulk delete customers
     * DELETE /api/mgr/customers/bulk
     *
     * @param array $data Request data (ids)
     * @return array Response
     */
    public function bulkDelete(array $data = []): array
    {
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Customer IDs array is required', 400)->getData();
        }

        // Sanitize IDs
        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return Response::error('No valid customer IDs provided', 400)->getData();
        }

        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $customer = $this->modx->getObject(msCustomer::class, $id);

            if (!$customer) {
                $failed++;
                continue;
            }

            // Delete related addresses
            $addresses = $this->modx->getIterator(\MiniShop3\Model\msCustomerAddress::class, ['customer_id' => $id]);
            foreach ($addresses as $address) {
                $address->remove();
            }

            // Delete related tokens
            $tokens = $this->modx->getIterator(\MiniShop3\Model\msCustomerToken::class, ['customer_id' => $id]);
            foreach ($tokens as $token) {
                $token->remove();
            }

            if ($customer->remove()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted === 0) {
            return Response::error('Failed to delete customers', 500)->getData();
        }

        return Response::success([
            'deleted' => $deleted,
            'failed' => $failed
        ], "Deleted {$deleted} customers")->getData();
    }

    /**
     * Format customer object for API response
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
     * Extract relation fields from grid configuration
     *
     * @param array $gridFields Grid field configuration
     * @return array Array of relation fields ['field_name' => config]
     */
    protected function extractRelationFields(array $gridFields): array
    {
        $relationFields = [];

        foreach ($gridFields as $field) {
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
     * Extract computed fields from grid configuration
     *
     * @param array $gridFields Grid field configuration
     * @return array Array of computed fields ['field_name' => config]
     */
    protected function extractComputedFields(array $gridFields): array
    {
        $computedFields = [];

        foreach ($gridFields as $field) {
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
     * Get aggregated data for relation fields
     *
     * @param array $customerIds Array of customer IDs
     * @param array $relationFields Configuration of relation fields
     * @return array Array [customer_id => [field_name => value]]
     */
    protected function fetchRelationData(array $customerIds, array $relationFields): array
    {
        if (empty($customerIds) || empty($relationFields)) {
            return [];
        }

        $result = [];

        foreach ($customerIds as $customerId) {
            $result[$customerId] = [];
            foreach ($relationFields as $fieldName => $config) {
                $result[$customerId][$fieldName] = 0;
            }
        }

        $customersTable = $this->modx->getTableName(msCustomer::class);

        foreach ($relationFields as $fieldName => $config) {
            $relationTable = $config['resolvedTableName'] ?? $config['table'] ?? null;
            $foreignKey = $config['foreignKey'] ?? null;
            $displayField = $config['displayField'] ?? null;
            $aggregation = $config['aggregation'] ?? null;

            if (!$relationTable || !$foreignKey || !$displayField) {
                continue;
            }

            if ($aggregation) {
                $selectExpr = "{$aggregation}({$relationTable}.{$displayField})";
            } else {
                $selectExpr = "{$relationTable}.{$displayField}";
            }

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

            foreach ($rows as $row) {
                $customerId = (int)$row['customer_id'];
                $value = $row['field_value'];

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
     * Compute value of computed field
     *
     * @param array $row Row data (customer)
     * @param array $config Computed field configuration
     * @return mixed Computed value
     */
    protected function computeField(array $row, array $config)
    {
        $className = $config['className'] ?? null;

        if (!$className) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[CustomersController] Computed field className not specified');
            return null;
        }

        if (!class_exists($className)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[CustomersController] Computed class not found: {$className}");
            return null;
        }

        $interfaces = class_implements($className);
        if (!isset($interfaces['MiniShop3\\Interfaces\\ComputedFieldInterface'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[CustomersController] Class {$className} must implement ComputedFieldInterface");
            return null;
        }

        try {
            $instance = new $className();
            return $instance->compute($row);
        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[CustomersController] Error computing field with {$className}: {$e->getMessage()}");
            return null;
        }
    }
}
