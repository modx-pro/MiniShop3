<?php

namespace MiniShop3\Services\Inventory;

/**
 * Caller context for reserve / commit / release. Idempotency is keyed by order_id + product.
 */
final class InventoryContext
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $origin = 'order',
    ) {
        if ($orderId <= 0) {
            throw new \InvalidArgumentException('InventoryContext orderId must be positive');
        }
    }
}
