<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Services\Shipment\ShipmentStoreInterface;

/**
 * @phpstan-import-type ShipmentRow from ShipmentStoreInterface
 */
final class InMemoryShipmentStore implements ShipmentStoreInterface
{
    /** @var array<int, ShipmentRow> */
    private array $rows = [];

    /** @var array<string, true> */
    private array $events = [];

    private int $nextId = 1;

    public function create(
        int $orderId,
        int $deliveryId,
        string $status,
        ?string $provider,
        array $meta = [],
    ): array {
        $existing = $this->findByOrderId($orderId);
        if ($existing !== null) {
            return $existing;
        }
        $now = time();
        $row = [
            'id' => $this->nextId++,
            'order_id' => $orderId,
            'delivery_id' => $deliveryId,
            'status' => $status,
            'tracking_number' => null,
            'external_id' => null,
            'provider' => $provider,
            'carrier' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'last_event_id' => null,
            'meta' => $meta,
            'createdon' => $now,
            'updatedon' => $now,
        ];
        $this->rows[$row['id']] = $row;

        return $row;
    }

    public function update(int $id, array $fields): array
    {
        $row = $this->rows[$id] ?? null;
        if ($row === null) {
            throw new \RuntimeException('shipment not found');
        }
        foreach ($fields as $key => $value) {
            if ($key === 'id' || $key === 'createdon') {
                continue;
            }
            $row[$key] = $value;
        }
        $row['updatedon'] = time();
        $this->rows[$id] = $row;

        return $row;
    }

    public function findById(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function findByOrderId(int $orderId): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['order_id'] === $orderId) {
                return $row;
            }
        }

        return null;
    }

    public function findByExternalId(string $provider, string $externalId, ?int $deliveryId = null): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['provider'] !== $provider || $row['external_id'] !== $externalId) {
                continue;
            }
            if ($deliveryId !== null && $row['delivery_id'] !== $deliveryId) {
                continue;
            }

            return $row;
        }

        return null;
    }

    public function hasEvent(int $shipmentId, string $providerEventId): bool
    {
        return isset($this->events[$this->eventKey($shipmentId, $providerEventId)]);
    }

    public function recordEvent(int $shipmentId, string $providerEventId): void
    {
        $this->events[$this->eventKey($shipmentId, $providerEventId)] = true;
    }

    private function eventKey(int $shipmentId, string $providerEventId): string
    {
        return $shipmentId . "\0" . $providerEventId;
    }
}
