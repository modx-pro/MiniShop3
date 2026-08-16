<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

/**
 * Normalized storefront filters for GET /api/v1/product/list (#564).
 */
final class ProductCatalogFilterSpec
{
    /**
     * @param list<int> $parentIds From `parents` (before nested expand)
     * @param list<int> $vendorIds
     * @param array<string, list<string>> $options key => values (OR within key)
     */
    public function __construct(
        public readonly array $parentIds = [],
        public readonly bool $nested = false,
        public readonly ?float $priceMin = null,
        public readonly ?float $priceMax = null,
        public readonly bool $inStock = false,
        public readonly ?int $stockMin = null,
        public readonly array $vendorIds = [],
        public readonly bool $flagNew = false,
        public readonly bool $flagPopular = false,
        public readonly bool $flagFavorite = false,
        public readonly array $options = [],
    ) {
    }

    public function hasParents(): bool
    {
        return $this->parentIds !== [];
    }

    public function hasDataFilters(): bool
    {
        return $this->priceMin !== null
            || $this->priceMax !== null
            || $this->inStock
            || $this->stockMin !== null
            || $this->vendorIds !== []
            || $this->flagNew
            || $this->flagPopular
            || $this->flagFavorite
            || $this->options !== [];
    }
}
