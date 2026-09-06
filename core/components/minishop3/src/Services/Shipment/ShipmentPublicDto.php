<?php

declare(strict_types=1);

namespace MiniShop3\Services\Shipment;

/**
 * Allowlist for cabinet / storefront. No provider secrets, meta, or class names.
 *
 * @phpstan-import-type ShipmentRow from ShipmentStoreInterface
 */
final class ShipmentPublicDto
{
    /**
     * @param ShipmentRow $row
     * @return array<string, mixed>
     */
    public static function fromRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'order_id' => (int) $row['order_id'],
            'delivery_id' => (int) $row['delivery_id'],
            'status' => (string) $row['status'],
            'tracking_number' => $row['tracking_number'],
            'carrier' => $row['carrier'] ?? null,
            'shipped_at' => $row['shipped_at'],
            'delivered_at' => $row['delivered_at'],
        ];
    }
}
