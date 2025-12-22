<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msVendor;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;
use MODX\Revolution\modResource;

/**
 * API controller for vendor management (Manager API)
 *
 * Handles CRUD operations for vendors in admin panel.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class VendorsController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get list of vendors with pagination and search
     * GET /api/mgr/vendors
     *
     * @param array $params URL parameters (start, limit, query)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 20);
        $query = trim($params['query'] ?? '');

        // If limit=0, return all vendors
        $returnAll = $limit === 0;

        $criteria = [];

        if (!empty($query)) {
            $criteria[] = [
                'name:LIKE' => "%{$query}%",
                'OR:description:LIKE' => "%{$query}%",
                'OR:country:LIKE' => "%{$query}%",
                'OR:email:LIKE' => "%{$query}%",
            ];
        }

        $total = $this->modx->getCount(msVendor::class, $criteria);

        $q = $this->modx->newQuery(msVendor::class, $criteria);
        $q->leftJoin(modResource::class, 'Resource', 'msVendor.resource_id = Resource.id');
        $q->select($this->modx->getSelectColumns(msVendor::class, 'msVendor'));
        $q->select(['pagetitle' => 'Resource.pagetitle']);

        if (!$returnAll) {
            $q->limit($limit, $start);
        }
        $q->sortby('name', 'ASC');

        $results = [];
        foreach ($this->modx->getIterator(msVendor::class, $q) as $vendor) {
            $results[] = $this->formatVendor($vendor);
        }

        return Response::success([
            'results' => $results,
            'total' => $total
        ])->getData();
    }

    /**
     * Get specific vendor
     * GET /api/mgr/vendors/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Vendor ID is required', 400)->getData();
        }

        $vendor = $this->modx->getObject(msVendor::class, $id);

        if (!$vendor) {
            return Response::error('Vendor not found', 404)->getData();
        }

        return Response::success($this->formatVendor($vendor))->getData();
    }

    /**
     * Create new vendor
     * POST /api/mgr/vendors
     *
     * @param array $data Request data
     * @return array Response
     */
    public function create(array $data = []): array
    {
        if (empty($data['name'])) {
            return Response::error('Vendor name is required', 400)->getData();
        }

        $vendor = $this->modx->newObject(msVendor::class);

        $allowedFields = ['name', 'description', 'country', 'logo', 'address', 'phone', 'email', 'resource_id', 'properties'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $vendor->set($field, $data[$field]);
            }
        }

        if (!$vendor->save()) {
            return Response::error('Failed to create vendor', 500)->getData();
        }

        return Response::success($this->formatVendor($vendor), 'Vendor created successfully')->getData();
    }

    /**
     * Update vendor
     * PUT /api/mgr/vendors/{id}
     *
     * @param array $data Update data
     * @return array Response
     */
    public function update(array $data = []): array
    {
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            return Response::error('Vendor ID is required', 400)->getData();
        }

        $vendor = $this->modx->getObject(msVendor::class, $id);

        if (!$vendor) {
            return Response::error('Vendor not found', 404)->getData();
        }

        $allowedFields = ['name', 'description', 'country', 'logo', 'address', 'phone', 'email', 'resource_id', 'properties'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $vendor->set($field, $data[$field]);
            }
        }

        if (!$vendor->save()) {
            return Response::error('Failed to save vendor', 500)->getData();
        }

        return Response::success($this->formatVendor($vendor), 'Vendor updated successfully')->getData();
    }

    /**
     * Delete vendor
     * DELETE /api/mgr/vendors/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Vendor ID is required', 400)->getData();
        }

        $vendor = $this->modx->getObject(msVendor::class, $id);

        if (!$vendor) {
            return Response::error('Vendor not found', 404)->getData();
        }

        if (!$vendor->remove()) {
            return Response::error('Failed to delete vendor', 500)->getData();
        }

        return Response::success([], 'Vendor deleted successfully')->getData();
    }

    /**
     * Bulk delete vendors
     * DELETE /api/mgr/vendors/bulk
     *
     * @param array $data Request data (ids)
     * @return array Response
     */
    public function bulkDelete(array $data = []): array
    {
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Vendor IDs array is required', 400)->getData();
        }

        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return Response::error('No valid vendor IDs provided', 400)->getData();
        }

        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $vendor = $this->modx->getObject(msVendor::class, $id);

            if (!$vendor) {
                $failed++;
                continue;
            }

            if ($vendor->remove()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted === 0) {
            return Response::error('Failed to delete vendors', 500)->getData();
        }

        return Response::success([
            'deleted' => $deleted,
            'failed' => $failed
        ], "Deleted {$deleted} vendors")->getData();
    }

    /**
     * Format vendor object for API response
     *
     * @param msVendor $vendor
     * @return array
     */
    protected function formatVendor(msVendor $vendor): array
    {
        return [
            'id' => $vendor->get('id'),
            'name' => $vendor->get('name'),
            'description' => $vendor->get('description'),
            'country' => $vendor->get('country'),
            'logo' => $vendor->get('logo'),
            'address' => $vendor->get('address'),
            'phone' => $vendor->get('phone'),
            'email' => $vendor->get('email'),
            'resource_id' => $vendor->get('resource_id'),
            'pagetitle' => $vendor->get('pagetitle'),
            'properties' => $vendor->get('properties'),
        ];
    }
}
