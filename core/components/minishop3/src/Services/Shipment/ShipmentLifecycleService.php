<?php

declare(strict_types=1);

namespace MiniShop3\Services\Shipment;

use MiniShop3\Controllers\Delivery\ShipmentWebhookEvent;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Order\OrderStatusService;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modX;

/**
 * Coordinates shipment state. Order status changes go through OrderStatusService.
 *
 * @phpstan-import-type ShipmentRow from ShipmentStoreInterface
 */
class ShipmentLifecycleService
{
    private const ALLOWED_TRANSITIONS = [
        ShipmentStatus::PREPARING => [
            ShipmentStatus::PREPARING,
            ShipmentStatus::SHIPPED,
            ShipmentStatus::CANCELLED,
            ShipmentStatus::FAILED,
        ],
        ShipmentStatus::SHIPPED => [
            ShipmentStatus::SHIPPED,
            ShipmentStatus::IN_TRANSIT,
            ShipmentStatus::DELIVERED,
            ShipmentStatus::CANCELLED,
            ShipmentStatus::FAILED,
            ShipmentStatus::RETURNED,
        ],
        ShipmentStatus::IN_TRANSIT => [
            ShipmentStatus::IN_TRANSIT,
            ShipmentStatus::DELIVERED,
            ShipmentStatus::FAILED,
            ShipmentStatus::RETURNED,
        ],
        ShipmentStatus::DELIVERED => [
            ShipmentStatus::DELIVERED,
            ShipmentStatus::RETURNED,
        ],
        ShipmentStatus::CANCELLED => [ShipmentStatus::CANCELLED],
        ShipmentStatus::FAILED => [ShipmentStatus::FAILED],
        ShipmentStatus::RETURNED => [ShipmentStatus::RETURNED],
    ];

    private const BLOCKED_META_KEYS = [
        'password',
        'secret',
        'token',
        'api_key',
        'secret_key',
        'properties',
        'class',
        'authorization',
    ];

    public function __construct(
        private readonly ShipmentStoreInterface $store,
        private readonly modX $modx,
        private readonly OrderStatusService $orderStatus,
    ) {
    }

    public static function isEnabled(modX $modx): bool
    {
        $value = $modx->getOption('ms3_shipment_enabled', null, false);

        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * @param array<string, mixed> $meta
     * @return ShipmentRow
     */
    public function create(int $orderId, array $meta = []): array
    {
        $existing = $this->store->findByOrderId($orderId);
        if ($existing !== null) {
            return $existing;
        }
        $order = $this->modx->getObject(msOrder::class, ['id' => $orderId]);
        if (!$order instanceof msOrder) {
            throw new ShipmentLifecycleException(
                'ms3_err_order_nf',
                ['order_id' => $orderId],
                ShipmentLifecycleException::KIND_NOT_FOUND
            );
        }
        $deliveryId = (int) $order->get('delivery_id');
        if ($deliveryId <= 0) {
            throw new ShipmentLifecycleException('ms3_err_delivery_id_required');
        }
        if (!$this->fire('msOnBeforeCreateShipment', ['order_id' => $orderId, 'delivery_id' => $deliveryId])) {
            throw new ShipmentLifecycleException('ms3_err_shipment_cancelled');
        }
        $row = $this->store->create(
            $orderId,
            $deliveryId,
            ShipmentStatus::PREPARING,
            $this->providerFromOrder($order),
            $this->sanitizeMeta($meta)
        );
        $this->fire('msOnCreateShipment', ['shipment' => $row]);

        return $row;
    }

    /**
     * @return ShipmentRow|null
     */
    public function findByOrderId(int $orderId): ?array
    {
        return $this->store->findByOrderId($orderId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publicListForOrder(int $orderId): array
    {
        $row = $this->findByOrderId($orderId);
        if ($row === null) {
            return [];
        }

        return [ShipmentPublicDto::fromRow($row)];
    }

    /**
     * @return ShipmentRow
     */
    public function setTracking(int $shipmentId, string $trackingNumber, ?string $eventId = null): array
    {
        $shipment = $this->requireShipment($shipmentId);
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            throw new ShipmentLifecycleException('ms3_err_shipment_tracking_invalid');
        }
        if ($this->isReplay($shipment, $eventId)) {
            return $shipment;
        }
        if (!$this->fire('msOnBeforeUpdateShipmentTracking', [
            'shipment' => $shipment,
            'tracking_number' => $trackingNumber,
        ])) {
            throw new ShipmentLifecycleException('ms3_err_shipment_cancelled');
        }
        $fields = ['tracking_number' => $trackingNumber];
        if ($this->isNonEmpty($eventId)) {
            $fields['last_event_id'] = $eventId;
        }
        $updated = $this->store->update($shipmentId, $fields);
        $this->rememberEvent($shipmentId, $eventId);
        $this->fire('msOnUpdateShipmentTracking', ['shipment' => $updated]);

        return $updated;
    }

    /**
     * @return ShipmentRow
     */
    public function transition(int $shipmentId, string $target, ?string $eventId = null): array
    {
        $shipment = $this->requireShipment($shipmentId);
        if ($this->isReplay($shipment, $eventId)) {
            return $shipment;
        }
        $this->assertTransition($shipment['status'], $target);
        if (!$this->fire('msOnBeforeChangeShipmentStatus', [
            'shipment' => $shipment,
            'status' => $target,
        ])) {
            throw new ShipmentLifecycleException('ms3_err_shipment_cancelled');
        }
        $fields = ['status' => $target] + $this->transitionTimestamps($shipment, $target);
        if ($this->isNonEmpty($eventId)) {
            $fields['last_event_id'] = $eventId;
        }
        $updated = $this->store->update($shipmentId, $fields);
        $this->rememberEvent($shipmentId, $eventId);
        $this->syncOrderStatus($updated['order_id'], $target);
        $this->fire('msOnChangeShipmentStatus', ['shipment' => $updated]);

        return $updated;
    }

    /**
     * @return ShipmentRow
     */
    public function applyProviderEvent(ShipmentWebhookEvent $event, int $deliveryId, string $provider): array
    {
        $shipment = $this->resolveShipment($event, $deliveryId, $provider);
        if ($shipment !== null && $this->isReplay($shipment, $event->providerEventId)) {
            return $shipment;
        }
        $from = $shipment['status'] ?? ShipmentStatus::PREPARING;
        $this->assertTransition($from, $event->eventType);
        if ($shipment === null) {
            $shipment = $this->create((int) $event->orderId);
        }

        $trackingNumber = $event->trackingNumber !== null ? trim($event->trackingNumber) : '';
        $trackingChanged = $trackingNumber !== '' && $trackingNumber !== (string) $shipment['tracking_number'];
        $fields = $this->providerEventFields($shipment, $event);
        $fields['status'] = $event->eventType;
        $fields += $this->transitionTimestamps($shipment, $event->eventType);
        if ($this->isNonEmpty($event->providerEventId)) {
            $fields['last_event_id'] = $event->providerEventId;
        }
        if ($trackingNumber !== '') {
            $fields['tracking_number'] = $trackingNumber;
        }

        if ($trackingChanged && !$this->fire('msOnBeforeUpdateShipmentTracking', [
            'shipment' => $shipment,
            'tracking_number' => $trackingNumber,
        ])) {
            throw new ShipmentLifecycleException('ms3_err_shipment_cancelled');
        }
        if (!$this->fire('msOnBeforeChangeShipmentStatus', [
            'shipment' => $shipment,
            'status' => $event->eventType,
        ])) {
            throw new ShipmentLifecycleException('ms3_err_shipment_cancelled');
        }

        $updated = $this->store->update($shipment['id'], $fields);
        $this->rememberEvent((int) $updated['id'], $event->providerEventId);
        $this->syncOrderStatus($updated['order_id'], $event->eventType);
        if ($trackingChanged) {
            $this->fire('msOnUpdateShipmentTracking', ['shipment' => $updated]);
        }
        $this->fire('msOnChangeShipmentStatus', ['shipment' => $updated]);

        return $updated;
    }

    /**
     * @return ShipmentRow
     */
    private function resolveShipment(ShipmentWebhookEvent $event, int $deliveryId, string $provider): ?array
    {
        if ($this->isNonEmpty($event->externalId)) {
            $byExternal = $this->store->findByExternalId($provider, $event->externalId, $deliveryId);
            if ($byExternal !== null) {
                return $byExternal;
            }
        }
        if ($event->orderId === null || $event->orderId <= 0) {
            throw new ShipmentLifecycleException(
                'ms3_err_shipment_nf',
                [],
                ShipmentLifecycleException::KIND_NOT_FOUND
            );
        }
        $existing = $this->store->findByOrderId($event->orderId);
        if ($existing !== null) {
            if ($existing['delivery_id'] !== $deliveryId) {
                throw new ShipmentLifecycleException(
                    'ms3_err_shipment_event_conflict',
                    ['from' => 'delivery_id', 'to' => (string) $deliveryId],
                    ShipmentLifecycleException::KIND_CONFLICT
                );
            }

            return $existing;
        }
        $this->assertOrderBoundToDelivery($event->orderId, $deliveryId);

        return null;
    }

    private function assertOrderBoundToDelivery(int $orderId, int $deliveryId): void
    {
        $order = $this->modx->getObject(msOrder::class, ['id' => $orderId]);
        if (!$order instanceof msOrder) {
            throw new ShipmentLifecycleException(
                'ms3_err_order_nf',
                ['order_id' => $orderId],
                ShipmentLifecycleException::KIND_NOT_FOUND
            );
        }
        if ((int) $order->get('delivery_id') !== $deliveryId) {
            throw new ShipmentLifecycleException(
                'ms3_err_shipment_event_conflict',
                ['from' => 'delivery_id', 'to' => (string) $deliveryId],
                ShipmentLifecycleException::KIND_CONFLICT
            );
        }
    }

    /**
     * @return ShipmentRow
     */
    private function requireShipment(int $id): array
    {
        $row = $this->store->findById($id);
        if ($row === null) {
            throw new ShipmentLifecycleException(
                'ms3_err_shipment_nf',
                ['id' => $id],
                ShipmentLifecycleException::KIND_NOT_FOUND
            );
        }

        return $row;
    }

    private function assertTransition(string $from, string $target): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];
        if (!in_array($target, $allowed, true)) {
            throw new ShipmentLifecycleException(
                'ms3_err_shipment_event_conflict',
                ['from' => $from, 'to' => $target],
                ShipmentLifecycleException::KIND_CONFLICT
            );
        }
    }

    private function syncOrderStatus(int $orderId, string $shipmentStatus): void
    {
        if (!self::isEnabled($this->modx)) {
            return;
        }
        $statusId = $this->orderStatusFor($shipmentStatus);
        if ($statusId <= 0) {
            return;
        }
        $order = $this->modx->getObject(msOrder::class, ['id' => $orderId]);
        if (!$order instanceof msOrder) {
            return;
        }
        if ((int) $order->get('status_id') === $statusId) {
            return;
        }
        $result = $this->orderStatus->change($orderId, $statusId);
        if ($result !== true) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                sprintf(
                    'ShipmentLifecycleService: order #%d status sync to %d failed: %s',
                    $orderId,
                    $statusId,
                    is_string($result) ? $result : 'unknown'
                )
            );
        }
    }

    private function orderStatusFor(string $shipmentStatus): int
    {
        return match ($shipmentStatus) {
            ShipmentStatus::SHIPPED => (int) $this->modx->getOption('ms3_status_sent', null, 4) ?: 4,
            ShipmentStatus::CANCELLED,
            ShipmentStatus::FAILED => (int) $this->modx->getOption('ms3_status_canceled', null, 5) ?: 5,
            default => (int) $this->modx->getOption('ms3_shipment_on_' . $shipmentStatus . '_status', null, 0),
        };
    }

    private function providerFromOrder(msOrder $order): ?string
    {
        $delivery = $order->getOne('Delivery');
        if ($delivery === null) {
            return null;
        }
        $class = $delivery->get('class');

        return is_string($class) && $class !== '' ? $class : null;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function sanitizeMeta(array $meta): array
    {
        $clean = [];
        foreach ($meta as $key => $value) {
            $name = strtolower((string) $key);
            if (in_array($name, self::BLOCKED_META_KEYS, true)) {
                continue;
            }
            if (is_array($value)) {
                $clean[(string) $key] = $this->sanitizeMeta($value);
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[(string) $key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param ShipmentRow $shipment
     * @return array<string, int>
     */
    private function transitionTimestamps(array $shipment, string $target): array
    {
        $now = time();
        $fields = [];
        if ($target === ShipmentStatus::SHIPPED || $target === ShipmentStatus::IN_TRANSIT) {
            $fields['shipped_at'] = $shipment['shipped_at'] ?? $now;
        }
        if ($target === ShipmentStatus::DELIVERED) {
            $fields['delivered_at'] = $shipment['delivered_at'] ?? $now;
            $fields['shipped_at'] = $shipment['shipped_at'] ?? $now;
        }

        return $fields;
    }

    /**
     * @param ShipmentRow $shipment
     * @return array<string, mixed>
     */
    private function providerEventFields(array $shipment, ShipmentWebhookEvent $event): array
    {
        $fields = [];
        if ($this->isNonEmpty($event->externalId)) {
            $fields['external_id'] = $event->externalId;
        }
        if ($this->isNonEmpty($event->carrier)) {
            $fields['carrier'] = $event->carrier;
        }
        if ($event->payload !== []) {
            $fields['meta'] = array_merge($shipment['meta'], $this->sanitizeMeta($event->payload));
        }

        return $fields;
    }

    /**
     * @param ShipmentRow $shipment
     */
    private function isReplay(array $shipment, ?string $eventId): bool
    {
        if (!$this->isNonEmpty($eventId)) {
            return false;
        }
        if ($this->store->hasEvent((int) $shipment['id'], $eventId)) {
            return true;
        }
        if ($shipment['last_event_id'] === $eventId) {
            $this->store->recordEvent((int) $shipment['id'], $eventId);

            return true;
        }

        return false;
    }

    private function rememberEvent(int $shipmentId, ?string $eventId): void
    {
        if ($this->isNonEmpty($eventId)) {
            $this->store->recordEvent($shipmentId, $eventId);
        }
    }

    private function isNonEmpty(?string $value): bool
    {
        return $value !== null && $value !== '';
    }

    /**
     * @param array<string, mixed> $params
     */
    private function fire(string $event, array $params): bool
    {
        if (!$this->modx->services->has('ms3')) {
            return true;
        }
        $ms3 = $this->modx->services->get('ms3');
        if (!is_object($ms3) || !isset($ms3->utils)) {
            return true;
        }
        EventGate::clearReturnedValues($this->modx);
        $response = $ms3->utils->invokeEvent($event, $params);

        return !empty($response['success']);
    }
}
