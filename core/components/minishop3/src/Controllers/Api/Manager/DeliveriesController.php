<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API controller for delivery management (Manager API)
 *
 * Handles CRUD operations for deliveries in admin panel.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class DeliveriesController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get list of deliveries with pagination and search
     * GET /api/mgr/deliveries
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
        $gridFields = $gridConfig ? $gridConfig->getGridConfig('deliveries') : [];

        $criteria = [];

        if (!empty($query)) {
            $criteria[] = [
                'name:LIKE' => "%{$query}%",
                'OR:description:LIKE' => "%{$query}%",
            ];
        }

        // Filter by active status
        foreach ($params as $key => $value) {
            if (strpos($key, 'filter_') === 0 && $value !== '' && $value !== null) {
                $fieldName = substr($key, 7);
                if ($fieldName === 'active') {
                    $criteria['active'] = (int)$value;
                } else {
                    $criteria[$fieldName . ':LIKE'] = "%{$value}%";
                }
            }
        }

        $total = $this->modx->getCount(msDelivery::class, $criteria);

        $q = $this->modx->newQuery(msDelivery::class, $criteria);
        $q->limit($limit, $start);
        $q->sortby('position', 'ASC');

        $results = [];
        foreach ($this->modx->getIterator(msDelivery::class, $q) as $delivery) {
            $results[] = $this->formatDelivery($delivery);
        }

        return Response::success([
            'results' => $results,
            'total' => $total
        ])->getData();
    }

    /**
     * Get specific delivery
     * GET /api/mgr/deliveries/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Delivery ID is required', 400)->getData();
        }

        $delivery = $this->modx->getObject(msDelivery::class, $id);

        if (!$delivery) {
            return Response::error('Delivery not found', 404)->getData();
        }

        $data = $this->formatDelivery($delivery);

        // Add payments for this delivery
        $payments = [];
        foreach ($this->modx->getIterator(msDeliveryMember::class, ['delivery_id' => $id]) as $member) {
            $payments[] = $member->get('payment_id');
        }
        $data['payments'] = $payments;

        return Response::success($data)->getData();
    }

    /**
     * Create new delivery
     * POST /api/mgr/deliveries
     *
     * @param array $data Request data
     * @return array Response
     */
    public function create(array $data = []): array
    {
        if (empty($data['name'])) {
            return Response::error('Delivery name is required', 400)->getData();
        }

        $delivery = $this->modx->newObject(msDelivery::class);

        $allowedFields = ['name', 'description', 'price', 'weight_price', 'distance_price',
                          'logo', 'position', 'active', 'class', 'properties',
                          'validation_rules', 'free_delivery_amount'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $delivery->set($field, $data[$field]);
            }
        }

        // Set defaults
        if (!isset($data['position'])) {
            $maxPosition = 0;
            $last = $this->modx->getObject(msDelivery::class, [], ['sortby' => 'position', 'sortdir' => 'DESC', 'limit' => 1]);
            if ($last) {
                $maxPosition = (int)$last->get('position');
            }
            $delivery->set('position', $maxPosition + 1);
        }

        if (!$delivery->save()) {
            return Response::error('Failed to create delivery', 500)->getData();
        }

        // Handle payments assignment
        if (isset($data['payments']) && is_array($data['payments'])) {
            $this->updateDeliveryPayments($delivery->get('id'), $data['payments']);
        }

        return Response::success($this->formatDelivery($delivery), 'Delivery created successfully')->getData();
    }

    /**
     * Update delivery
     * PUT /api/mgr/deliveries/{id}
     *
     * @param array $data Update data
     * @return array Response
     */
    public function update(array $data = []): array
    {
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            return Response::error('Delivery ID is required', 400)->getData();
        }

        $delivery = $this->modx->getObject(msDelivery::class, $id);

        if (!$delivery) {
            return Response::error('Delivery not found', 404)->getData();
        }

        $allowedFields = ['name', 'description', 'price', 'weight_price', 'distance_price',
                          'logo', 'position', 'active', 'class', 'properties',
                          'validation_rules', 'free_delivery_amount'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $delivery->set($field, $data[$field]);
            }
        }

        if (!$delivery->save()) {
            return Response::error('Failed to save delivery', 500)->getData();
        }

        // Handle payments assignment
        if (isset($data['payments']) && is_array($data['payments'])) {
            $this->updateDeliveryPayments($id, $data['payments']);
        }

        return Response::success($this->formatDelivery($delivery), 'Delivery updated successfully')->getData();
    }

    /**
     * Delete delivery
     * DELETE /api/mgr/deliveries/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Delivery ID is required', 400)->getData();
        }

        $delivery = $this->modx->getObject(msDelivery::class, $id);

        if (!$delivery) {
            return Response::error('Delivery not found', 404)->getData();
        }

        // Delete related payment members
        foreach ($this->modx->getIterator(msDeliveryMember::class, ['delivery_id' => $id]) as $member) {
            $member->remove();
        }

        if (!$delivery->remove()) {
            return Response::error('Failed to delete delivery', 500)->getData();
        }

        return Response::success([], 'Delivery deleted successfully')->getData();
    }

    /**
     * Bulk delete deliveries
     * DELETE /api/mgr/deliveries/bulk
     *
     * @param array $data Request data (ids)
     * @return array Response
     */
    public function bulkDelete(array $data = []): array
    {
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Delivery IDs array is required', 400)->getData();
        }

        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return Response::error('No valid delivery IDs provided', 400)->getData();
        }

        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $delivery = $this->modx->getObject(msDelivery::class, $id);

            if (!$delivery) {
                $failed++;
                continue;
            }

            // Delete related payment members
            foreach ($this->modx->getIterator(msDeliveryMember::class, ['delivery_id' => $id]) as $member) {
                $member->remove();
            }

            if ($delivery->remove()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted === 0) {
            return Response::error('Failed to delete deliveries', 500)->getData();
        }

        return Response::success([
            'deleted' => $deleted,
            'failed' => $failed
        ], "Deleted {$deleted} deliveries")->getData();
    }

    /**
     * Update positions (drag-n-drop reorder)
     * PUT /api/mgr/deliveries/positions
     *
     * @param array $data Request data (positions: [id => position])
     * @return array Response
     */
    public function updatePositions(array $data = []): array
    {
        $positions = $data['positions'] ?? [];

        if (empty($positions) || !is_array($positions)) {
            return Response::error('Positions array is required', 400)->getData();
        }

        $updated = 0;
        foreach ($positions as $id => $position) {
            $delivery = $this->modx->getObject(msDelivery::class, (int)$id);
            if ($delivery) {
                $delivery->set('position', (int)$position);
                if ($delivery->save()) {
                    $updated++;
                }
            }
        }

        return Response::success(['updated' => $updated], "Updated {$updated} positions")->getData();
    }

    /**
     * Format delivery object for API response
     *
     * @param msDelivery $delivery
     * @return array
     */
    protected function formatDelivery(msDelivery $delivery): array
    {
        return [
            'id' => $delivery->get('id'),
            'name' => $delivery->get('name'),
            'description' => $delivery->get('description'),
            'price' => $delivery->get('price'),
            'weight_price' => (float)$delivery->get('weight_price'),
            'distance_price' => (float)$delivery->get('distance_price'),
            'logo' => $delivery->get('logo'),
            'position' => (int)$delivery->get('position'),
            'active' => (bool)$delivery->get('active'),
            'class' => $delivery->get('class'),
            'properties' => $delivery->get('properties'),
            'validation_rules' => $delivery->get('validation_rules'),
            'free_delivery_amount' => (float)$delivery->get('free_delivery_amount'),
        ];
    }

    /**
     * Get payments for specific delivery
     * GET /api/mgr/deliveries/{id}/payments
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function getPayments(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Delivery ID is required', 400)->getData();
        }

        $results = [];
        foreach ($this->modx->getIterator(msDeliveryMember::class, ['delivery_id' => $id]) as $member) {
            $results[] = [
                'delivery_id' => $member->get('delivery_id'),
                'payment_id' => $member->get('payment_id'),
            ];
        }

        return Response::success(['results' => $results])->getData();
    }

    /**
     * Add payment to delivery
     * POST /api/mgr/deliveries/{id}/payments
     *
     * @param array $data Request data (delivery_id, payment_id)
     * @return array Response
     */
    public function addPayment(array $data = []): array
    {
        $deliveryId = (int)($data['delivery_id'] ?? 0);
        $paymentId = (int)($data['payment_id'] ?? 0);

        if (!$deliveryId || !$paymentId) {
            return Response::error('Delivery ID and Payment ID are required', 400)->getData();
        }

        // Check if relationship already exists
        $existing = $this->modx->getObject(msDeliveryMember::class, [
            'delivery_id' => $deliveryId,
            'payment_id' => $paymentId
        ]);

        if ($existing) {
            return Response::success([], 'Payment already linked to delivery')->getData();
        }

        $member = $this->modx->newObject(msDeliveryMember::class);
        $member->set('delivery_id', $deliveryId);
        $member->set('payment_id', $paymentId);

        if (!$member->save()) {
            return Response::error('Failed to add payment to delivery', 500)->getData();
        }

        return Response::success([], 'Payment added to delivery')->getData();
    }

    /**
     * Remove payment from delivery
     * DELETE /api/mgr/deliveries/{id}/payments/{payment_id}
     *
     * @param array $params URL parameters (id, payment_id)
     * @return array Response
     */
    public function removePayment(array $params = []): array
    {
        $deliveryId = (int)($params['id'] ?? 0);
        $paymentId = (int)($params['payment_id'] ?? 0);

        if (!$deliveryId || !$paymentId) {
            return Response::error('Delivery ID and Payment ID are required', 400)->getData();
        }

        $member = $this->modx->getObject(msDeliveryMember::class, [
            'delivery_id' => $deliveryId,
            'payment_id' => $paymentId
        ]);

        if (!$member) {
            return Response::error('Payment link not found', 404)->getData();
        }

        if (!$member->remove()) {
            return Response::error('Failed to remove payment from delivery', 500)->getData();
        }

        return Response::success([], 'Payment removed from delivery')->getData();
    }

    /**
     * Update delivery-payment relationships
     *
     * @param int $deliveryId
     * @param array $paymentIds
     */
    protected function updateDeliveryPayments(int $deliveryId, array $paymentIds): void
    {
        // Remove existing relationships
        foreach ($this->modx->getIterator(msDeliveryMember::class, ['delivery_id' => $deliveryId]) as $member) {
            $member->remove();
        }

        // Add new relationships
        foreach ($paymentIds as $paymentId) {
            $member = $this->modx->newObject(msDeliveryMember::class);
            $member->set('delivery_id', $deliveryId);
            $member->set('payment_id', (int)$paymentId);
            $member->save();
        }
    }
}
