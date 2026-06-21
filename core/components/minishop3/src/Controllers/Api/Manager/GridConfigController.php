<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Router\Response;
use MiniShop3\Services\GridConfigService;
use MiniShop3\Services\GridEditorReferenceRegistry;
use MODX\Revolution\modX;

/**
 * API Controller for grid configuration management
 */
class GridConfigController
{
    /** @var modX */
    protected $modx;

    /** @var GridConfigService */
    protected $service;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->service = $modx->services->get('ms3_grid_config');
    }

    /**
     * Get grid configuration
     *
     * GET /api/mgr/grid-config/{grid_key}
     *
     * @param array $params
     *   - grid_key: string - Grid identifier
     *   - include_hidden: bool - Include hidden fields (for config editor)
     * @return array
     */
    public function getConfig(array $params): array
    {
        $gridKey = $params['grid_key'] ?? '';

        if (empty($gridKey)) {
            return Response::error('grid_key is required')->getData();
        }

        // For grid config editor we need all fields including hidden
        $includeHidden = !empty($params['include_hidden']);
        $config = $this->service->getGridConfig($gridKey, $includeHidden);

        $payload = ['columns' => $config];
        if ($gridKey === 'category-products') {
            $payload['editor_references'] = GridEditorReferenceRegistry::listForClient();
        }

        return Response::success($payload)->getData();
    }

    /**
     * Save grid configuration
     *
     * PUT /api/mgr/grid-config/{grid_key}
     *
     * @param array $data
     * @return array
     */
    public function saveConfig(array $data): array
    {
        $gridKey = $data['grid_key'] ?? '';
        $fields = $data['fields'] ?? [];

        if (empty($gridKey)) {
            return Response::error('grid_key is required')->getData();
        }

        if (empty($fields) || !is_array($fields)) {
            return Response::error('fields array is required')->getData();
        }

        $success = $this->service->saveGridConfig($gridKey, $fields);

        if ($success) {
            return Response::success(['message' => 'Grid configuration saved'])->getData();
        } else {
            return Response::error('Failed to save grid configuration')->getData();
        }
    }

    /**
     * Add new field to grid configuration
     *
     * POST /api/mgr/grid-config/{grid_key}/field
     *
     * @param array $data
     * @return array
     */
    public function addField(array $data): array
    {
        $gridKey = $data['grid_key'] ?? '';

        if (empty($gridKey)) {
            return Response::error('grid_key is required')->getData();
        }

        $result = $this->service->addField($gridKey, $data);

        if ($result['success']) {
            return Response::success([
                'message' => $result['message'] ?? 'Field created successfully',
                'field' => $result['field'] ?? null
            ])->getData();
        } else {
            return Response::error($result['message'] ?? 'Failed to create field')->getData();
        }
    }

    /**
     * Update existing field in grid configuration
     *
     * PUT /api/mgr/grid-config/{grid_key}/field/{field_name}
     *
     * @param array $data
     * @return array
     */
    public function updateField(array $data): array
    {
        $gridKey = $data['grid_key'] ?? '';
        $fieldName = $data['field_name'] ?? '';

        if (empty($gridKey) || empty($fieldName)) {
            return Response::error('grid_key and field_name are required')->getData();
        }

        $result = $this->service->updateField($gridKey, $fieldName, $data);

        if ($result['success']) {
            return Response::success([
                'message' => $result['message'] ?? 'Field updated successfully',
                'field' => $result['field'] ?? null
            ])->getData();
        } else {
            return Response::error($result['message'] ?? 'Failed to update field')->getData();
        }
    }

    /**
     * Delete field from grid configuration
     *
     * DELETE /api/mgr/grid-config/{grid_key}/{field_name}
     *
     * @param array $params
     * @return array
     */
    public function deleteField(array $params): array
    {
        $gridKey = $params['grid_key'] ?? '';
        $fieldName = $params['field_name'] ?? '';

        if (empty($gridKey)) {
            return Response::error('grid_key is required')->getData();
        }

        if (empty($fieldName)) {
            return Response::error('field_name is required')->getData();
        }

        $result = $this->service->deleteField($gridKey, $fieldName);

        if ($result['success']) {
            return Response::success([
                'message' => $result['message'] ?? 'Field deleted successfully'
            ])->getData();
        } else {
            return Response::error($result['message'] ?? 'Failed to delete field')->getData();
        }
    }
}
