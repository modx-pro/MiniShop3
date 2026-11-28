<?php

namespace MiniShop3\Services\Inventory;

/**
 * Product-level default for heldByKey() (#1001).
 *
 * Use this trait in adjustable inventory implementations that do not track
 * variants; stores with per-SKU rows override it with their own map.
 */
trait AdjustableInventoryKeyTrait
{
    /**
     * Active holds keyed by {@see InventoryKey::identity()}.
     *
     * @return array<string, array{qty: float, state: string, key: InventoryKey}>
     */
    public function heldByKey(int $orderId): array
    {
        $held = [];
        foreach ($this->heldByProduct($orderId) as $productId => $row) {
            $key = new InventoryKey((int) $productId);
            $held[$key->identity()] = [
                'qty' => round((float) ($row['qty'] ?? 0), 3),
                'state' => (string) ($row['state'] ?? ''),
                'key' => $key,
            ];
        }

        return $held;
    }
}
