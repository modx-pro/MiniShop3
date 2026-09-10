<?php

declare(strict_types=1);

namespace MiniShop3\Services\Shipment;

/**
 * Persistence for ms3_shipments.
 *
 * @phpstan-type ShipmentRow array{
 *     id: int,
 *     order_id: int,
 *     delivery_id: int,
 *     status: string,
 *     tracking_number: ?string,
 *     external_id: ?string,
 *     provider: ?string,
 *     carrier: ?string,
 *     shipped_at: ?int,
 *     delivered_at: ?int,
 *     last_event_id: ?string,
 *     meta: array<string, mixed>,
 *     createdon: int,
 *     updatedon: int
 * }
 */
interface ShipmentStoreInterface
{
    /**
     * @param array<string, mixed> $meta
     * @return ShipmentRow
     */
    public function create(
        int $orderId,
        int $deliveryId,
        string $status,
        ?string $provider,
        array $meta = [],
    ): array;

    /**
     * @param array<string, mixed> $fields
     * @return ShipmentRow
     */
    public function update(int $id, array $fields): array;

    /**
     * @return ShipmentRow|null
     */
    public function findById(int $id): ?array;

    /**
     * @return ShipmentRow|null
     */
    public function findByOrderId(int $orderId): ?array;

    /**
     * @return ShipmentRow|null
     */
    public function findByExternalId(string $provider, string $externalId, ?int $deliveryId = null): ?array;

    public function hasEvent(int $shipmentId, string $providerEventId): bool;

    public function recordEvent(int $shipmentId, string $providerEventId): void;
}
