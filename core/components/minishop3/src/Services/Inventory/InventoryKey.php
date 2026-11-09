<?php

namespace MiniShop3\Services\Inventory;

/**
 * Stock identity: product_id on msProductData plus an optional variant
 * (SKU / options hash) supplied by an addon via `msOnInventoryResolveKey`.
 *
 * Without addons, variantKey is null and accounting is by product, exactly
 * as before (#1001). Empty string is normalized to null.
 */
final class InventoryKey
{
    public readonly ?string $variantKey;

    public function __construct(
        public readonly int $productId,
        ?string $variantKey = null,
    ) {
        if ($productId <= 0) {
            throw new \InvalidArgumentException('InventoryKey productId must be positive');
        }
        $variantKey = trim((string) ($variantKey ?? ''));
        $this->variantKey = $variantKey === '' ? null : $variantKey;
    }

    public function isVariant(): bool
    {
        return $this->variantKey !== null;
    }

    /**
     * Stable grouping identity for order lines: "123" or "123|sku-hash".
     */
    public function identity(): string
    {
        return $this->variantKey === null
            ? (string) $this->productId
            : $this->productId . '|' . $this->variantKey;
    }

    /**
     * Ledger value for variant_key column: '' is product-level (see migration).
     */
    public function ledgerVariantKey(): string
    {
        return $this->variantKey ?? '';
    }
}
