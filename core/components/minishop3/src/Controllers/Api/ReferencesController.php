<?php

namespace MiniShop3\Controllers\Api;

use MiniShop3\Router\Response;

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
            $query = $this->modx->newQuery('MiniShop3\\Model\\msVendor');

            $query->select(['id', 'name']);

            $query->sortby('name', 'ASC');

            $searchQuery = $_GET['query'] ?? null;
            if (!empty($searchQuery)) {
                $query->where([
                    'name:LIKE' => "%{$searchQuery}%",
                    'OR:description:LIKE' => "%{$searchQuery}%",
                ]);
            }

            $collection = $this->modx->getCollection('MiniShop3\\Model\\msVendor', $query);

            $vendors = [];
            foreach ($collection as $vendor) {
                $vendors[] = [
                    'id' => (int)$vendor->get('id'),
                    'name' => $vendor->get('name'),
                ];
            }

            return Response::success([
                'vendors' => $vendors,
                'total' => count($vendors)
            ]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ReferencesController] ' . $e->getMessage());
            return Response::error('Failed to load vendors: ' . $e->getMessage(), 500);
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
                return Response::error('Field name is required', 400);
            }

            $modelMeta = $this->modx->getFields('MiniShop3\\Model\\msProductData');
            if (!isset($modelMeta[$fieldName])) {
                return Response::error("Field '{$fieldName}' not found in msProductData", 400);
            }

            $tableName = $this->modx->getTableName('MiniShop3\\Model\\msProductData');

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $fieldName)) {
                return Response::error("Invalid field name", 400);
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
            return Response::error('Failed to load autocomplete: ' . $e->getMessage(), 500);
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
                return Response::error('Option key is required', 400);
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
            return Response::error('Failed to load options: ' . $e->getMessage(), 500);
        }
    }
}
