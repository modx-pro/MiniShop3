<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msOrderStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API controller for order status management (Manager API)
 *
 * Handles CRUD operations for order statuses in admin panel.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class StatusesController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get list of statuses with pagination and search
     * GET /api/mgr/statuses
     *
     * @param array $params URL parameters (start, limit, query)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 20);
        $query = trim($params['query'] ?? '');

        $returnAll = $limit === 0;

        $criteria = [];

        if (!empty($query)) {
            $criteria[] = [
                'name:LIKE' => "%{$query}%",
                'OR:description:LIKE' => "%{$query}%",
            ];
        }

        $total = $this->modx->getCount(msOrderStatus::class, $criteria);

        $q = $this->modx->newQuery(msOrderStatus::class, $criteria);

        if (!$returnAll) {
            $q->limit($limit, $start);
        }
        $q->sortby('position', 'ASC');

        $results = [];
        foreach ($this->modx->getIterator(msOrderStatus::class, $q) as $status) {
            $results[] = $this->formatStatus($status);
        }

        return Response::success([
            'results' => $results,
            'total' => $total
        ])->getData();
    }

    /**
     * Get specific status
     * GET /api/mgr/statuses/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Status ID is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $status = $this->modx->getObject(msOrderStatus::class, $id);

        if (!$status) {
            return Response::error('Status not found', Response::HTTP_NOT_FOUND)->getData();
        }

        return Response::success($this->formatStatus($status))->getData();
    }

    /**
     * Create new status
     * POST /api/mgr/statuses
     *
     * @param array $data Request data
     * @return array Response
     */
    public function create(array $data = []): array
    {
        if (empty($data['name'])) {
            return Response::error('Status name is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        // Get max position for new status
        $maxPosition = 0;
        $q = $this->modx->newQuery(msOrderStatus::class);
        $q->select('MAX(position) as max_position');
        if ($q->prepare() && $q->stmt->execute()) {
            $row = $q->stmt->fetch(\PDO::FETCH_ASSOC);
            $maxPosition = (int)($row['max_position'] ?? 0);
        }

        $status = $this->modx->newObject(msOrderStatus::class);

        $allowedFields = ['name', 'description', 'color', 'active', 'final', 'fixed', 'editable'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                // Convert boolean-like values
                if (in_array($field, ['active', 'final', 'fixed', 'editable'])) {
                    $value = $value ? 1 : 0;
                }
                $status->set($field, $value);
            }
        }

        // Set position to end
        $status->set('position', $maxPosition + 1);

        if (!$status->save()) {
            return Response::error('Failed to create status', Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success($this->formatStatus($status), 'Status created successfully')->getData();
    }

    /**
     * Update status
     * PUT /api/mgr/statuses/{id}
     *
     * @param array $data Update data
     * @return array Response
     */
    public function update(array $data = []): array
    {
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            return Response::error('Status ID is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $status = $this->modx->getObject(msOrderStatus::class, $id);

        if (!$status) {
            return Response::error('Status not found', Response::HTTP_NOT_FOUND)->getData();
        }

        $allowedFields = ['name', 'description', 'color', 'active', 'final', 'fixed', 'editable'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                // Convert boolean-like values
                if (in_array($field, ['active', 'final', 'fixed', 'editable'])) {
                    $value = $value ? 1 : 0;
                }
                $status->set($field, $value);
            }
        }

        if (!$status->save()) {
            return Response::error('Failed to save status', Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success($this->formatStatus($status), 'Status updated successfully')->getData();
    }

    /**
     * Delete status
     * DELETE /api/mgr/statuses/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Status ID is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $status = $this->modx->getObject(msOrderStatus::class, $id);

        if (!$status) {
            return Response::error('Status not found', Response::HTTP_NOT_FOUND)->getData();
        }

        // Check if status is used by orders
        $ordersCount = $this->modx->getCount('MiniShop3\\Model\\msOrder', ['status_id' => $id]);
        if ($ordersCount > 0) {
            return Response::error("Cannot delete status: {$ordersCount} orders are using it", Response::HTTP_BAD_REQUEST)->getData();
        }

        if (!$status->remove()) {
            return Response::error('Failed to delete status', Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], 'Status deleted successfully')->getData();
    }

    /**
     * Bulk delete statuses
     * DELETE /api/mgr/statuses/bulk
     *
     * @param array $data Request data (ids)
     * @return array Response
     */
    public function bulkDelete(array $data = []): array
    {
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Status IDs array is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return Response::error('No valid status IDs provided', Response::HTTP_BAD_REQUEST)->getData();
        }

        $deleted = 0;
        $failed = 0;
        $inUse = 0;

        foreach ($ids as $id) {
            $status = $this->modx->getObject(msOrderStatus::class, $id);

            if (!$status) {
                $failed++;
                continue;
            }

            // Check if status is used by orders
            $ordersCount = $this->modx->getCount('MiniShop3\\Model\\msOrder', ['status_id' => $id]);
            if ($ordersCount > 0) {
                $inUse++;
                continue;
            }

            if ($status->remove()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted === 0) {
            if ($inUse > 0) {
                return Response::error("Cannot delete: {$inUse} statuses are in use by orders", Response::HTTP_BAD_REQUEST)->getData();
            }
            return Response::error('Failed to delete statuses', Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([
            'deleted' => $deleted,
            'failed' => $failed,
            'in_use' => $inUse
        ], "Deleted {$deleted} statuses")->getData();
    }

    /**
     * Reorder statuses (drag-drop sort)
     * POST /api/mgr/statuses/sort
     *
     * @param array $data Request data (ids - array of status IDs in new order)
     * @return array Response
     */
    public function sort(array $data = []): array
    {
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Status IDs array is required for sorting', Response::HTTP_BAD_REQUEST)->getData();
        }

        $position = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $status = $this->modx->getObject(msOrderStatus::class, $id);
                if ($status) {
                    $status->set('position', $position);
                    $status->save();
                    $position++;
                }
            }
        }

        return Response::success([], 'Statuses reordered successfully')->getData();
    }

    /**
     * Format status object for API response
     *
     * @param msOrderStatus $status
     * @return array
     */
    protected function formatStatus(msOrderStatus $status): array
    {
        return [
            'id' => $status->get('id'),
            'name' => $status->get('name'),
            'description' => $status->get('description'),
            'color' => $status->get('color'),
            'active' => (bool)$status->get('active'),
            'final' => (bool)$status->get('final'),
            'fixed' => (bool)$status->get('fixed'),
            'position' => (int)$status->get('position'),
            'editable' => (bool)$status->get('editable'),
        ];
    }
}
