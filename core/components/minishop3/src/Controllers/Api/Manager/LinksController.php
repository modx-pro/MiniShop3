<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msLink;
use MiniShop3\Model\msProductLink;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API controller for product links management (Manager API)
 *
 * Handles CRUD operations for product link types in admin panel.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class LinksController
{
    protected modX $modx;

    /**
     * Available link types
     */
    protected array $linkTypes = [
        'one_to_one',
        'one_to_many',
        'many_to_one',
        'many_to_many'
    ];

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get list of links with pagination and search
     * GET /api/mgr/links
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

        $total = $this->modx->getCount(msLink::class, $criteria);

        $q = $this->modx->newQuery(msLink::class, $criteria);

        if (!$returnAll) {
            $q->limit($limit, $start);
        }
        $q->sortby('id', 'ASC');

        $results = [];
        foreach ($this->modx->getIterator(msLink::class, $q) as $link) {
            $results[] = $this->formatLink($link);
        }

        return Response::success([
            'results' => $results,
            'total' => $total
        ])->getData();
    }

    /**
     * Get specific link
     * GET /api/mgr/links/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Link ID is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $link = $this->modx->getObject(msLink::class, $id);

        if (!$link) {
            return Response::error('Link not found', Response::HTTP_NOT_FOUND)->getData();
        }

        return Response::success($this->formatLink($link))->getData();
    }

    /**
     * Get available link types
     * GET /api/mgr/links/types
     *
     * @return array Response
     */
    public function getTypes(): array
    {
        $types = [];
        foreach ($this->linkTypes as $type) {
            $types[] = [
                'value' => $type,
                'label' => $this->modx->lexicon('ms3_link_' . $type),
                'description' => $this->modx->lexicon('ms3_link_' . $type . '_desc')
            ];
        }

        return Response::success([
            'results' => $types,
            'total' => count($types)
        ])->getData();
    }

    /**
     * Create new link
     * POST /api/mgr/links
     *
     * @param array $data Request data
     * @return array Response
     */
    public function create(array $data = []): array
    {
        if (empty($data['name'])) {
            return Response::error('Link name is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        if (empty($data['type'])) {
            return Response::error('Link type is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        if (!in_array($data['type'], $this->linkTypes)) {
            return Response::error('Invalid link type', Response::HTTP_BAD_REQUEST)->getData();
        }

        $link = $this->modx->newObject(msLink::class);

        $allowedFields = ['name', 'type', 'description'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $link->set($field, $data[$field]);
            }
        }

        if (!$link->save()) {
            return Response::error('Failed to create link', Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success($this->formatLink($link), 'Link created successfully')->getData();
    }

    /**
     * Update link
     * PUT /api/mgr/links/{id}
     *
     * @param array $data Update data
     * @return array Response
     */
    public function update(array $data = []): array
    {
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            return Response::error('Link ID is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $link = $this->modx->getObject(msLink::class, $id);

        if (!$link) {
            return Response::error('Link not found', Response::HTTP_NOT_FOUND)->getData();
        }

        // Note: type cannot be changed after creation
        $allowedFields = ['name', 'description'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $link->set($field, $data[$field]);
            }
        }

        if (!$link->save()) {
            return Response::error('Failed to save link', Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success($this->formatLink($link), 'Link updated successfully')->getData();
    }

    /**
     * Delete link
     * DELETE /api/mgr/links/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Link ID is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $link = $this->modx->getObject(msLink::class, $id);

        if (!$link) {
            return Response::error('Link not found', Response::HTTP_NOT_FOUND)->getData();
        }

        // Check if link is used by products
        $usageCount = $this->modx->getCount(msProductLink::class, ['link_id' => $id]);
        if ($usageCount > 0) {
            return Response::error("Cannot delete link: it is used by {$usageCount} product connections", Response::HTTP_BAD_REQUEST)->getData();
        }

        if (!$link->remove()) {
            return Response::error('Failed to delete link', Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], 'Link deleted successfully')->getData();
    }

    /**
     * Bulk delete links
     * DELETE /api/mgr/links/bulk
     *
     * @param array $data Request data (ids)
     * @return array Response
     */
    public function bulkDelete(array $data = []): array
    {
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Link IDs array is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return Response::error('No valid link IDs provided', Response::HTTP_BAD_REQUEST)->getData();
        }

        $deleted = 0;
        $failed = 0;
        $inUse = 0;

        foreach ($ids as $id) {
            $link = $this->modx->getObject(msLink::class, $id);

            if (!$link) {
                $failed++;
                continue;
            }

            // Check if link is used by products
            $usageCount = $this->modx->getCount(msProductLink::class, ['link_id' => $id]);
            if ($usageCount > 0) {
                $inUse++;
                continue;
            }

            if ($link->remove()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted === 0) {
            if ($inUse > 0) {
                return Response::error("Cannot delete: {$inUse} links are in use", Response::HTTP_BAD_REQUEST)->getData();
            }
            return Response::error('Failed to delete links', Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([
            'deleted' => $deleted,
            'failed' => $failed,
            'in_use' => $inUse
        ], "Deleted {$deleted} links")->getData();
    }

    /**
     * Format link object for API response
     *
     * @param msLink $link
     * @return array
     */
    protected function formatLink(msLink $link): array
    {
        $type = $link->get('type');
        return [
            'id' => $link->get('id'),
            'name' => $link->get('name'),
            'type' => $type,
            'type_label' => $this->modx->lexicon('ms3_link_' . $type),
            'description' => $link->get('description'),
        ];
    }
}
