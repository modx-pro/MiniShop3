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

    /**
     * Soft price facet: bounds ignore selected price_min/price_max so the slider does not collapse.
     */
    public function withoutPriceBounds(): self
    {
        if ($this->priceMin === null && $this->priceMax === null) {
            return $this;
        }

        return $this->with(['priceMin' => null, 'priceMax' => null]);
    }

    /**
     * Soft option facet: buckets for $key ignore selected values of that key only.
     */
    public function withoutOptionKey(string $key): self
    {
        if (!array_key_exists($key, $this->options)) {
            return $this;
        }

        $options = $this->options;
        unset($options[$key]);

        return $this->with(['options' => $options]);
    }

    /**
     * Soft vendor facet: buckets ignore selected vendor_id filter.
     */
    public function withoutVendors(): self
    {
        if ($this->vendorIds === []) {
            return $this;
        }

        return $this->with(['vendorIds' => []]);
    }

    /**
     * @param array{
     *     priceMin?: float|null,
     *     priceMax?: float|null,
     *     vendorIds?: list<int>,
     *     options?: array<string, list<string>>
     * } $overrides
     */
    private function with(array $overrides): self
    {
        return new self(
            parentIds: $this->parentIds,
            nested: $this->nested,
            priceMin: array_key_exists('priceMin', $overrides) ? $overrides['priceMin'] : $this->priceMin,
            priceMax: array_key_exists('priceMax', $overrides) ? $overrides['priceMax'] : $this->priceMax,
            inStock: $this->inStock,
            stockMin: $this->stockMin,
            vendorIds: array_key_exists('vendorIds', $overrides) ? $overrides['vendorIds'] : $this->vendorIds,
            flagNew: $this->flagNew,
            flagPopular: $this->flagPopular,
            flagFavorite: $this->flagFavorite,
            options: array_key_exists('options', $overrides) ? $overrides['options'] : $this->options,
        );
    }
}
