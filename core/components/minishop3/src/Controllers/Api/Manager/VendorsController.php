<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msVendor;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modSystemEvent;
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

    /** @var string[] Cached extra field keys for msVendor */
    protected array $extraFieldKeys = [];

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->loadExtraFieldsMap();
        $this->extraFieldKeys = $this->getExtraFieldKeys();
    }

    /**
     * Load extra fields into xPDO map
     */
    protected function loadExtraFieldsMap(): void
    {
        $ms3 = $this->modx->services->get('ms3');
        if ($ms3) {
            $ms3->loadMap();
        }
    }

    /**
     * Get active extra field keys for msVendor
     *
     * @return string[]
     */
    protected function getExtraFieldKeys(): array
    {
        $keys = [];
        $query = $this->modx->newQuery(msExtraField::class);
        $query->where([
            'class' => 'MiniShop3\\Model\\msVendor',
            'active' => true,
        ]);

        foreach ($this->modx->getIterator(msExtraField::class, $query) as $field) {
            $keys[] = $field->get('key');
        }

        return $keys;
    }

    /**
     * Get allowed fields for create/update including extra fields
     *
     * @return string[]
     */
    protected function getAllowedFields(): array
    {
        $baseFields = ['name', 'description', 'country', 'logo', 'address', 'phone', 'email', 'resource_id', 'position', 'properties'];

        return array_merge($baseFields, $this->extraFieldKeys);
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

        // Filter by name, country, email (from grid filters)
        if (!empty($params['filter_name'])) {
            $criteria[] = ['name:LIKE' => "%{$params['filter_name']}%"];
        }
        if (!empty($params['filter_country'])) {
            $criteria[] = ['country:LIKE' => "%{$params['filter_country']}%"];
        }
        if (!empty($params['filter_email'])) {
            $criteria[] = ['email:LIKE' => "%{$params['filter_email']}%"];
        }

        // Search query (searches name, description, country, email)
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
        $q->sortby('position', 'ASC');
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
            return Response::error('Vendor ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $vendor = $this->modx->getObject(msVendor::class, $id);

        if (!$vendor) {
            return Response::error('Vendor not found', HttpStatus::NOT_FOUND)->getData();
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
            return Response::error('Vendor name is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $vendor = $this->modx->newObject(msVendor::class);

        $allowedFields = $this->getAllowedFields();

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $vendor->set($field, $data[$field]);
            }
        }

        // Fire before event
        $modeNew = defined(modSystemEvent::class . '::MODE_NEW')
            ? modSystemEvent::MODE_NEW
            : 'new';
        $eventResponse = $this->invokeEvent('msOnBeforeVendorCreate', [
            'mode' => $modeNew,
            'data' => $data,
            'object' => $vendor,
        ]);
        if (!$eventResponse['success']) {
            return Response::error((string)$eventResponse['message'], HttpStatus::BAD_REQUEST)->getData();
        }
        // Plugins may patch payload fields via $modx->event->returnedValues.
        $data = array_merge($data, $eventResponse['values']);

        if (!$vendor->save()) {
            return Response::error('Failed to create vendor', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        // Fire after event
        $this->invokeEvent('msOnVendorCreate', [
            'mode' => $modeNew,
            'data' => $data,
            'object' => $vendor,
        ]);

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
            return Response::error('Vendor ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $vendor = $this->modx->getObject(msVendor::class, $id);

        if (!$vendor) {
            return Response::error('Vendor not found', HttpStatus::NOT_FOUND)->getData();
        }

        $allowedFields = $this->getAllowedFields();

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $vendor->set($field, $data[$field]);
            }
        }

        // Fire before event
        $modeUpd = defined(modSystemEvent::class . '::MODE_UPD')
            ? modSystemEvent::MODE_UPD
            : 'upd';
        $eventResponse = $this->invokeEvent('msOnBeforeVendorUpdate', [
            'mode' => $modeUpd,
            'data' => $data,
            'object' => $vendor,
        ]);
        if (!$eventResponse['success']) {
            return Response::error((string)$eventResponse['message'], HttpStatus::BAD_REQUEST)->getData();
        }
        // Plugins may patch payload fields via $modx->event->returnedValues.
        $data = array_merge($data, $eventResponse['values']);

        if (!$vendor->save()) {
            return Response::error('Failed to save vendor', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        // Fire after event
        $this->invokeEvent('msOnVendorUpdate', [
            'mode' => $modeUpd,
            'data' => $data,
            'object' => $vendor,
        ]);

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
            return Response::error('Vendor ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $vendor = $this->modx->getObject(msVendor::class, $id);

        if (!$vendor) {
            return Response::error('Vendor not found', HttpStatus::NOT_FOUND)->getData();
        }

        // Fire before event (no `mode`: removal is not an update)
        $eventResponse = $this->invokeEvent('msOnBeforeVendorDelete', [
            'object' => $vendor,
        ]);
        if (!$eventResponse['success']) {
            return Response::error((string)$eventResponse['message'], HttpStatus::BAD_REQUEST)->getData();
        }

        if (!$vendor->remove()) {
            return Response::error('Failed to delete vendor', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        // Fire after event
        $this->invokeEvent('msOnVendorDelete', [
            'object' => $vendor,
        ]);

        return Response::success([], 'Vendor deleted successfully')->getData();
    }

    /**
     * Reorder vendors (drag-drop sort)
     * POST /api/mgr/vendors/sort
     *
     * @param array $data Request data (ids - array of vendor IDs in new order)
     * @return array Response
     */
    public function sort(array $data = []): array
    {
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Vendor IDs array is required for sorting', HttpStatus::BAD_REQUEST)->getData();
        }

        $position = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $vendor = $this->modx->getObject(msVendor::class, $id);
                if ($vendor) {
                    $vendor->set('position', $position);
                    $vendor->save();
                    $position++;
                }
            }
        }

        return Response::success([], 'Vendors reordered successfully')->getData();
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
            return Response::error('Vendor IDs array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return Response::error('No valid vendor IDs provided', HttpStatus::BAD_REQUEST)->getData();
        }

        // Load objects once: plugins written for single-delete events can read
        // $object/$objects instead of only IDs (#847).
        $objects = [];
        foreach ($ids as $id) {
            $vendor = $this->modx->getObject(msVendor::class, $id);
            if ($vendor) {
                $objects[] = $vendor;
            }
        }

        // Fire before bulk delete event (no `mode`: removal is not an update).
        $eventResponse = $this->invokeEvent('msOnBeforeVendorDelete', [
            'ids' => $ids,
            'objects' => $objects,
        ]);
        if (!$eventResponse['success']) {
            return Response::error((string)$eventResponse['message'], HttpStatus::BAD_REQUEST)->getData();
        }

        $deleted = 0;
        $failed = count($ids) - count($objects);

        foreach ($objects as $vendor) {
            if ($vendor->remove()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        // Fire after bulk delete event
        $this->invokeEvent('msOnVendorDelete', [
            'ids' => $ids,
            'objects' => $objects,
            'deleted' => $deleted,
            'failed' => $failed,
        ]);

        if ($deleted === 0) {
            return Response::error('Failed to delete vendors', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
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
        $data = $vendor->toArray();

        // pagetitle comes from JOIN, not from msVendor fields
        $data['pagetitle'] = $vendor->get('pagetitle');

        return $data;
    }

    /**
     * Helper to invoke MODX events with normalized response.
     */
    protected function invokeEvent(string $eventName, array $params = []): array
    {
        $params['controller'] = $this;
        EventGate::clearReturnedValues($this->modx);
        $response = $this->modx->invokeEvent($eventName, $params);
        $message = EventGate::normalizeMessage($response);
        $returnedValues = EventGate::getReturnedValues($this->modx);

        return [
            'success' => !EventGate::isCancelled($response) && $message === '',
            'message' => $message,
            'data' => EventGate::mergeReturnedValues($params, $returnedValues),
            'values' => $returnedValues,
        ];
    }
}

