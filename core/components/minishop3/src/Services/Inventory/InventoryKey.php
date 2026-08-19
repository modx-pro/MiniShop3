<?php

namespace MiniShop3\Services\Inventory;

/**
 * Stock identity for the current core: product_id on msProductData.
 *
 * $variantKey is reserved for a future SKU / options hash. This issue does not
 * introduce product variants; callers may pass null.
 */
final class InventoryKey
{
    public function __construct(
        public readonly int $productId,
        public readonly ?string $variantKey = null,
    ) {
        if ($productId <= 0) {
            throw new \InvalidArgumentException('InventoryKey productId must be positive');
        }
    }
}
