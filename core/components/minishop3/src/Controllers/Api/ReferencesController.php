<?php

namespace MiniShop3\Controllers\Api;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Product\ProductLinkService;
use MiniShop3\Services\Settings\SettingsComboListService;

/**
 * API controller for working with reference data (vendors, categories, etc)
 */
class ReferencesController extends BaseApiController
{
    /**
     * GET /api/mgr/references/vendors
     * Get list of vendors for combo/select
     *
     * @param array $params
     * @return Response
     */
    public function getVendors(array $params): Response
    {
        try {
            /** @var SettingsComboListService $comboList */
            $comboList = $this->modx->services->get('ms3_settings_combo_list');
            $includeId = SettingsComboListService::resolvePinnedIncludeId(
                (int) ($params['id'] ?? $_GET['id'] ?? 0),
                $params['limit'] ?? $_GET['limit'] ?? null
            );
            $vendors = $comboList->listVendorsForCombo(
                $includeId,
                trim((string) ($params['query'] ?? $_GET['query'] ?? ''))
            );

            $options = array_map(
                static fn(array $row): array => ['value' => $row['id'], 'label' => (string) $row['name']],
                $vendors
            );

            return Response::success([
                'vendors' => $vendors,
                'options' => $options,
                'total' => count($vendors),
            ]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ReferencesController] ' . $e->getMessage());
            return Response::error('Failed to load vendors: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/mgr/references/autocomplete
     * Get unique column values for autocomplete
     *
     * @param array $params
     * @return Response
     */
    public function getAutocomplete(array $params): Response
    {
        try {
            $fieldName = $_GET['name'] ?? null;
            $searchQuery = $_GET['query'] ?? null;

            if (empty($fieldName)) {
                return Response::error('Field name is required', HttpStatus::BAD_REQUEST);
            }

            $modelMeta = $this->modx->getFields('MiniShop3\\Model\\msProductData');
            if (!isset($modelMeta[$fieldName])) {
                return Response::error("Field '{$fieldName}' not found in msProductData", HttpStatus::BAD_REQUEST);
            }

            $tableName = $this->modx->getTableName('MiniShop3\\Model\\msProductData');

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $fieldName)) {
                return Response::error("Invalid field name", HttpStatus::BAD_REQUEST);
            }

            $sql = "SELECT DISTINCT `{$fieldName}` as `value`
                    FROM {$tableName}
                    WHERE `{$fieldName}` IS NOT NULL
                    AND `{$fieldName}` != ''";

            $params = [];

            if (!empty($searchQuery)) {
                $sql .= " AND `{$fieldName}` LIKE :searchQuery";
                $params['searchQuery'] = "%{$searchQuery}%";
            }

            $sql .= " ORDER BY `{$fieldName}` ASC LIMIT 50";

            $stmt = $this->modx->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $values = [];
            foreach ($results as $row) {
                if (!empty($row['value'])) {
                    $values[] = [
                        'value' => $row['value']
                    ];
                }
            }

            if (!empty($searchQuery)) {
                $found = false;
                foreach ($values as $v) {
                    if ($v['value'] === $searchQuery) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    array_unshift($values, ['value' => $searchQuery]);
                }
            }

            return Response::success([
                'values' => $values,
                'total' => count($values)
            ]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ReferencesController] ' . $e->getMessage());
            return Response::error('Failed to load autocomplete: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/mgr/references/options
     * Get product options for multiple selection (chips/multiselect)
     *
     * @param array $params
     * @return Response
     */
    public function getOptions(array $params): Response
    {
        try {
            $key = $_GET['key'] ?? null;
            $searchQuery = $_GET['query'] ?? null;
            $exclude = isset($_GET['exclude']) ? json_decode($_GET['exclude'], true) : [];

            if (empty($key)) {
                return Response::error('Option key is required', HttpStatus::BAD_REQUEST);
            }

            $key = preg_replace('#^options-#', '', $key);

            $tableName = $this->modx->getTableName('MiniShop3\\Model\\msProductOption');

            $sql = "SELECT DISTINCT `value`
                    FROM {$tableName}
                    WHERE `key` = :key
                    AND `value` IS NOT NULL
                    AND `value` != ''";

            $params = ['key' => $key];

            if (!empty($searchQuery)) {
                $sql .= " AND `value` LIKE :searchQuery";
                $params['searchQuery'] = "%{$searchQuery}%";
            }

            $sql .= " ORDER BY `value` ASC LIMIT 50";

            $stmt = $this->modx->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $values = [];
            foreach ($results as $row) {
                if (!empty($row['value']) && !in_array($row['value'], $exclude)) {
                    $values[] = [
                        'value' => $row['value']
                    ];
                }
            }

            if (!empty($searchQuery)) {
                $found = false;
                foreach ($values as $v) {
                    if ($v['value'] === $searchQuery) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    array_unshift($values, ['value' => $searchQuery]);
                }
            }

            return Response::success([
                'values' => $values,
                'total' => count($values)
            ]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ReferencesController] ' . $e->getMessage());
            return Response::error('Failed to load options: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/mgr/references/product-option-fields
     * Get list of msProductData fields that can be used as product options
     *
     * @param array $params
     * @return Response
     */
    public function getProductOptionFields(array $params): Response
    {
        try {
            // Fields that are typically used as product options (JSON type fields)
            $optionFields = [
                ['name' => 'color', 'label' => $this->modx->lexicon('ms3_product_color') ?: 'Color'],
                ['name' => 'size', 'label' => $this->modx->lexicon('ms3_product_size') ?: 'Size'],
            ];

            // Also check for any custom JSON fields from msModelField
            $customFields = $this->modx->getIterator('MiniShop3\\Model\\msModelField', [
                'model' => 'msProductData',
                'xtype' => 'ms3-combo-options',
                'visible' => 1,
            ]);

            foreach ($customFields as $field) {
                $fieldName = $field->get('name');
                // Avoid duplicates
                $exists = false;
                foreach ($optionFields as $of) {
                    if ($of['name'] === $fieldName) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $optionFields[] = [
                        'name' => $fieldName,
                        'label' => $field->get('label') ?: $fieldName,
                    ];
                }
            }

            return Response::success([
                'fields' => $optionFields,
                'total' => count($optionFields)
            ]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ReferencesController] ' . $e->getMessage());
            return Response::error('Failed to load product option fields: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/mgr/references/product-field-values
     * Get unique values for a specific msProductData JSON field (color, size, etc.)
     *
     * @param array $params
     * @return Response
     */
    public function getProductFieldValues(array $params): Response
    {
        try {
            $fieldName = $_GET['field'] ?? $params['field'] ?? null;
            $productId = $_GET['product_id'] ?? $params['product_id'] ?? null;
            $searchQuery = $_GET['query'] ?? null;

            if (empty($fieldName)) {
                return Response::error('Field name is required', HttpStatus::BAD_REQUEST);
            }

            if (empty($productId)) {
                return Response::error('Product ID is required', HttpStatus::BAD_REQUEST);
            }

            // Validate field name (alphanumeric and underscore only)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $fieldName)) {
                return Response::error('Invalid field name', HttpStatus::BAD_REQUEST);
            }

            // Known option fields in msProductData
            $allowedFields = ['color', 'size', 'tags', 'made_in'];

            // Also check msModelField for custom fields with ms3-combo-options xtype
            $customField = $this->modx->getObject('MiniShop3\\Model\\msModelField', [
                'model' => 'msProductData',
                'name' => $fieldName,
            ]);

            if (!in_array($fieldName, $allowedFields) && !$customField) {
                return Response::error("Field '{$fieldName}' is not available for options", HttpStatus::BAD_REQUEST);
            }

            $tableName = $this->modx->getTableName('MiniShop3\\Model\\msProductData');

            // Get values for specific product
            $sql = "SELECT `{$fieldName}` as `value`
                    FROM {$tableName}
                    WHERE `id` = :product_id
                    AND `{$fieldName}` IS NOT NULL
                    AND `{$fieldName}` != ''
                    AND `{$fieldName}` != '[]'
                    AND `{$fieldName}` != 'null'";

            $stmt = $this->modx->prepare($sql);
            $stmt->execute(['product_id' => (int)$productId]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Extract unique values from JSON arrays
            $uniqueValues = [];
            foreach ($results as $row) {
                $rawValue = $row['value'];
                if (empty($rawValue)) {
                    continue;
                }

                // Try to decode as JSON
                $decoded = json_decode($rawValue, true);
                if (is_array($decoded)) {
                    // It's a JSON array - extract all values
                    foreach ($decoded as $item) {
                        if (is_string($item) && !empty(trim($item))) {
                            $uniqueValues[trim($item)] = true;
                        } elseif (is_array($item) && isset($item['value'])) {
                            // Handle {value: "...", ...} format
                            $uniqueValues[trim($item['value'])] = true;
                        }
                    }
                } elseif (is_string($rawValue) && !empty(trim($rawValue))) {
                    // Plain string value
                    $uniqueValues[trim($rawValue)] = true;
                }
            }

            // Convert to array and sort
            $values = array_keys($uniqueValues);
            sort($values, SORT_STRING | SORT_FLAG_CASE);

            // Filter by search query if provided
            if (!empty($searchQuery)) {
                $searchLower = mb_strtolower($searchQuery);
                $values = array_filter($values, function($v) use ($searchLower) {
                    return mb_strpos(mb_strtolower($v), $searchLower) !== false;
                });
                $values = array_values($values);
            }

            // Limit results
            $values = array_slice($values, 0, 100);

            // Format for Select component
            $formatted = array_map(function($v) {
                return ['value' => $v, 'label' => $v];
            }, $values);

            return Response::success([
                'values' => $formatted,
                'total' => count($formatted)
            ]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ReferencesController] ' . $e->getMessage());
            return Response::error('Failed to load field values: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/mgr/references/link-types
     * msLink definitions for product-form / combo selects.
     */
    public function getLinkTypes(array $params): Response
    {
        try {
            $service = $this->modx->services->get('ms3_product_link_service');
            $linkService = $service instanceof ProductLinkService
                ? $service
                : new ProductLinkService($this->modx);
            $results = $linkService->listLinkTypes();

            return Response::success([
                'results' => $results,
                'total' => count($results),
            ]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ReferencesController] ' . $e->getMessage());

            return Response::error(
                'Failed to load link types: ' . $e->getMessage(),
                HttpStatus::INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * GET /api/mgr/references/products
     * Search products for autocomplete
     *
     * @param array $params
     * @return Response
     */
    public function searchProducts(array $params): Response
    {
        try {
            $searchQuery = $_GET['query'] ?? '';
            $limit = (int)($_GET['limit'] ?? 20);

            if (strlen($searchQuery) < 2) {
                return Response::success([
                    'results' => [],
                    'total' => 0
                ]);
            }

            $c = $this->modx->newQuery('MiniShop3\\Model\\msProduct');

            // Join with msProductData for additional fields
            $c->leftJoin('MiniShop3\\Model\\msProductData', 'Data', 'msProduct.id = Data.id');

            // class_key filter (getIterator doesn't call addDerivativeCriteria)
            $c->where(['msProduct.class_key' => 'MiniShop3\\Model\\msProduct']);

            // Search by pagetitle, article, or id
            $c->where([
                'msProduct.pagetitle:LIKE' => "%{$searchQuery}%",
                'OR:Data.article:LIKE' => "%{$searchQuery}%",
            ]);

            // If search query is numeric, also search by ID
            if (is_numeric($searchQuery)) {
                $c->orCondition([
                    'msProduct.id' => (int)$searchQuery
                ]);
            }

            // Only published and not deleted products
            $c->where([
                'msProduct.published' => 1,
                'msProduct.deleted' => 0,
            ]);

            // Select fields
            $c->select([
                'msProduct.id',
                'msProduct.pagetitle',
                'msProduct.parent',
                'Data.article',
                'Data.price',
                'Data.weight',
                'Data.thumb',
                'Data.image',
            ]);

            $c->sortby('msProduct.pagetitle', 'ASC');
            $c->limit($limit);

            $products = [];
            foreach ($this->modx->getIterator('MiniShop3\\Model\\msProduct', $c) as $product) {
                $data = $product->toArray();

                $products[] = [
                    'id' => (int)$data['id'],
                    'pagetitle' => $data['pagetitle'],
                    'article' => $data['article'] ?? '',
                    'price' => (float)($data['price'] ?? 0),
                    'weight' => (float)($data['weight'] ?? 0),
                    'image' => $data['thumb'] ?: $data['image'] ?: '',
                    'display' => $data['pagetitle'] . (!empty($data['article']) ? ' [' . $data['article'] . ']' : ''),
                ];
            }

            return Response::success([
                'results' => $products,
                'total' => count($products)
            ]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ReferencesController] ' . $e->getMessage());
            return Response::error('Failed to search products: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/mgr/references/customers
     * Search customers for autocomplete
     *
     * @param array $params
     * @return Response
     */
    public function searchCustomers(array $params): Response
    {
        try {
            $searchQuery = $_GET['query'] ?? '';
            $limit = (int)($_GET['limit'] ?? 20);

            if (strlen($searchQuery) < 2) {
                return Response::success([
                    'results' => [],
                    'total' => 0
                ]);
            }

            $c = $this->modx->newQuery('MiniShop3\\Model\\msCustomer');

            // Search by first_name, last_name, email, phone
            if (is_numeric($searchQuery)) {
                // If numeric, also search by ID
                $c->where([
                    'id' => (int)$searchQuery,
                    'OR:phone:LIKE' => "%{$searchQuery}%",
                ]);
            } else {
                $c->where([
                    'first_name:LIKE' => "%{$searchQuery}%",
                    'OR:last_name:LIKE' => "%{$searchQuery}%",
                    'OR:email:LIKE' => "%{$searchQuery}%",
                    'OR:phone:LIKE' => "%{$searchQuery}%",
                ]);
            }

            // Only active customers
            $c->where(['is_active' => 1]);

            $c->select([
                'id', 'first_name', 'last_name', 'email', 'phone',
                'orders_count', 'total_spent'
            ]);

            $c->sortby('last_name', 'ASC');
            $c->sortby('first_name', 'ASC');
            $c->limit($limit);

            $customers = [];
            foreach ($this->modx->getIterator('MiniShop3\\Model\\msCustomer', $c) as $customer) {
                $data = $customer->toArray();

                // Build display name
                $displayParts = [];
                $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
                if ($name) {
                    $displayParts[] = $name;
                }
                if (!empty($data['email'])) {
                    $displayParts[] = $data['email'];
                } elseif (!empty($data['phone'])) {
                    $displayParts[] = $data['phone'];
                }

                $customers[] = [
                    'id' => (int)$data['id'],
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                    'email' => $data['email'] ?? '',
                    'phone' => $data['phone'] ?? '',
                    'orders_count' => (int)($data['orders_count'] ?? 0),
                    'total_spent' => (float)($data['total_spent'] ?? 0),
                    'display' => implode(' - ', $displayParts) ?: 'Customer #' . $data['id'],
                ];
            }

            return Response::success([
                'results' => $customers,
                'total' => count($customers)
            ]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ReferencesController] ' . $e->getMessage());
            return Response::error('Failed to search customers: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }
}
