<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msPayment;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API controller for payment management (Manager API)
 *
 * Handles CRUD operations for payments in admin panel.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class PaymentsController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get list of payments with pagination and search
     * GET /api/mgr/payments
     *
     * @param array $params URL parameters (start, limit, query)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 20);
        $query = trim($params['query'] ?? '');

        // If limit=0, return all payments (for delivery settings)
        $returnAll = $limit === 0;

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

        $total = $this->modx->getCount(msPayment::class, $criteria);

        $q = $this->modx->newQuery(msPayment::class, $criteria);
        if (!$returnAll) {
            $q->limit($limit, $start);
        }
        $q->sortby('position', 'ASC');

        $results = [];
        foreach ($this->modx->getIterator(msPayment::class, $q) as $payment) {
            $results[] = $this->formatPayment($payment);
        }

        return Response::success([
            'results' => $results,
            'total' => $total
        ])->getData();
    }

    /**
     * Get specific payment
     * GET /api/mgr/payments/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Payment ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $payment = $this->modx->getObject(msPayment::class, $id);

        if (!$payment) {
            return Response::error('Payment not found', HttpStatus::NOT_FOUND)->getData();
        }

        return Response::success($this->formatPayment($payment))->getData();
    }

    /**
     * Create new payment
     * POST /api/mgr/payments
     *
     * @param array $data Request data
     * @return array Response
     */
    public function create(array $data = []): array
    {
        if (empty($data['name'])) {
            return Response::error('Payment name is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $payment = $this->modx->newObject(msPayment::class);

        $allowedFields = ['name', 'description', 'price', 'logo', 'position', 'active', 'class', 'properties'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $payment->set($field, $data[$field]);
            }
        }

        // Set defaults
        if (!isset($data['position'])) {
            $maxPosition = 0;
            $q = $this->modx->newQuery(msPayment::class);
            $q->sortby('position', 'DESC');
            $q->limit(1);
            $last = $this->modx->getObject(msPayment::class, $q);
            if ($last) {
                $maxPosition = (int)$last->get('position');
            }
            $payment->set('position', $maxPosition + 1);
        }

        if (!$payment->save()) {
            return Response::error('Failed to create payment', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success($this->formatPayment($payment), 'Payment created successfully')->getData();
    }

    /**
     * Update payment
     * PUT /api/mgr/payments/{id}
     *
     * @param array $data Update data
     * @return array Response
     */
    public function update(array $data = []): array
    {
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            return Response::error('Payment ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $payment = $this->modx->getObject(msPayment::class, $id);

        if (!$payment) {
            return Response::error('Payment not found', HttpStatus::NOT_FOUND)->getData();
        }

        $allowedFields = ['name', 'description', 'price', 'logo', 'position', 'active', 'class', 'properties'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $payment->set($field, $data[$field]);
            }
        }

        if (!$payment->save()) {
            return Response::error('Failed to save payment', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success($this->formatPayment($payment), 'Payment updated successfully')->getData();
    }

    /**
     * Delete payment
     * DELETE /api/mgr/payments/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Payment ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $payment = $this->modx->getObject(msPayment::class, $id);

        if (!$payment) {
            return Response::error('Payment not found', HttpStatus::NOT_FOUND)->getData();
        }

        // Delete related delivery members
        foreach ($this->modx->getIterator(msDeliveryMember::class, ['payment_id' => $id]) as $member) {
            $member->remove();
        }

        if (!$payment->remove()) {
            return Response::error('Failed to delete payment', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], 'Payment deleted successfully')->getData();
    }

    /**
     * Reorder payments (drag-drop sort)
     * POST /api/mgr/payments/sort
     *
     * @param array $data Request data (ids - array of payment IDs in new order)
     * @return array Response
     */
    public function sort(array $data = []): array
    {
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Payment IDs array is required for sorting', HttpStatus::BAD_REQUEST)->getData();
        }

        $position = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $payment = $this->modx->getObject(msPayment::class, $id);
                if ($payment) {
                    $payment->set('position', $position);
                    $payment->save();
                    $position++;
                }
            }
        }

        return Response::success([], 'Payments reordered successfully')->getData();
    }

    /**
     * Bulk delete payments
     * DELETE /api/mgr/payments/bulk
     *
     * @param array $data Request data (ids)
     * @return array Response
     */
    public function bulkDelete(array $data = []): array
    {
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Payment IDs array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return Response::error('No valid payment IDs provided', HttpStatus::BAD_REQUEST)->getData();
        }

        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $payment = $this->modx->getObject(msPayment::class, $id);

            if (!$payment) {
                $failed++;
                continue;
            }

            // Delete related delivery members
            foreach ($this->modx->getIterator(msDeliveryMember::class, ['payment_id' => $id]) as $member) {
                $member->remove();
            }

            if ($payment->remove()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted === 0) {
            return Response::error('Failed to delete payments', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([
            'deleted' => $deleted,
            'failed' => $failed
        ], "Deleted {$deleted} payments")->getData();
    }

    /**
     * Update positions (drag-n-drop reorder)
     * PUT /api/mgr/payments/positions
     *
     * @param array $data Request data (positions: [id => position])
     * @return array Response
     */
    public function updatePositions(array $data = []): array
    {
        $positions = $data['positions'] ?? [];

        if (empty($positions) || !is_array($positions)) {
            return Response::error('Positions array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $updated = 0;
        foreach ($positions as $id => $position) {
            $payment = $this->modx->getObject(msPayment::class, (int)$id);
            if ($payment) {
                $payment->set('position', (int)$position);
                if ($payment->save()) {
                    $updated++;
                }
            }
        }

        return Response::success(['updated' => $updated], "Updated {$updated} positions")->getData();
    }

    /**
     * Format payment object for API response
     *
     * @param msPayment $payment
     * @return array
     */
    protected function formatPayment(msPayment $payment): array
    {
        return [
            'id' => $payment->get('id'),
            'name' => $payment->get('name'),
            'description' => $payment->get('description'),
            'price' => $payment->get('price'),
            'logo' => $payment->get('logo'),
            'position' => (int)$payment->get('position'),
            'active' => (bool)$payment->get('active'),
            'class' => $payment->get('class'),
            'properties' => $payment->get('properties'),
        ];
    }

    /**
     * Get deliveries for specific payment
     * GET /api/mgr/payments/{id}/deliveries
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function getDeliveries(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Payment ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $results = [];
        foreach ($this->modx->getIterator(msDeliveryMember::class, ['payment_id' => $id]) as $member) {
            $results[] = [
                'delivery_id' => $member->get('delivery_id'),
                'payment_id' => $member->get('payment_id'),
            ];
        }

        return Response::success(['results' => $results])->getData();
    }

    /**
     * Add delivery to payment
     * POST /api/mgr/payments/{id}/deliveries
     *
     * @param array $data Request data (payment_id, delivery_id)
     * @return array Response
     */
    public function addDelivery(array $data = []): array
    {
        $paymentId = (int)($data['payment_id'] ?? 0);
        $deliveryId = (int)($data['delivery_id'] ?? 0);

        if (!$paymentId || !$deliveryId) {
            return Response::error('Payment ID and Delivery ID are required', HttpStatus::BAD_REQUEST)->getData();
        }

        // Check if relationship already exists
        $existing = $this->modx->getObject(msDeliveryMember::class, [
            'delivery_id' => $deliveryId,
            'payment_id' => $paymentId
        ]);

        if ($existing) {
            return Response::success([], 'Delivery already linked to payment')->getData();
        }

        $member = $this->modx->newObject(msDeliveryMember::class);
        $member->set('delivery_id', $deliveryId);
        $member->set('payment_id', $paymentId);

        if (!$member->save()) {
            return Response::error('Failed to add delivery to payment', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], 'Delivery added to payment')->getData();
    }

    /**
     * Remove delivery from payment
     * DELETE /api/mgr/payments/{id}/deliveries/{delivery_id}
     *
     * @param array $params URL parameters (id, delivery_id)
     * @return array Response
     */
    public function removeDelivery(array $params = []): array
    {
        $paymentId = (int)($params['id'] ?? 0);
        $deliveryId = (int)($params['delivery_id'] ?? 0);

        if (!$paymentId || !$deliveryId) {
            return Response::error('Payment ID and Delivery ID are required', HttpStatus::BAD_REQUEST)->getData();
        }

        $member = $this->modx->getObject(msDeliveryMember::class, [
            'delivery_id' => $deliveryId,
            'payment_id' => $paymentId
        ]);

        if (!$member) {
            return Response::error('Delivery link not found', HttpStatus::NOT_FOUND)->getData();
        }

        if (!$member->remove()) {
            return Response::error('Failed to remove delivery from payment', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], 'Delivery removed from payment')->getData();
    }
}
