<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msOption;
use MiniShop3\Model\msProductOption;
use MiniShop3\Services\Category\CategoryProductScopeService;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Apply validated ProductCatalogFilterSpec onto an xPDO product list query.
 */
final class ProductCatalogFilterApplier
{
    /** Depth when nested=1 (matches ms3_products default depth window). */
    public const NESTED_DEPTH = 10;

    public function __construct(
        private modX $modx,
        private CategoryProductScopeService $scope,
    ) {
    }

    /**
     * @param bool $dedupeRows When true, GROUP BY product id after option JOINs (list pages).
     *                         Count queries must pass false and use COUNT(DISTINCT) instead.
     */
    public function apply(xPDOQuery $query, ProductCatalogFilterSpec $filters, bool $dedupeRows = true): void
    {
        $this->applyCategoryScope($query, $filters);
        $this->applyDataFilters($query, $filters);
        $this->applyOptionFilters($query, $filters, $dedupeRows);
    }

    private function applyCategoryScope(xPDOQuery $query, ProductCatalogFilterSpec $filters): void
    {
        if (!$filters->hasParents()) {
            return;
        }

        $depth = $filters->nested ? self::NESTED_DEPTH : 0;
        $parentsCsv = implode(',', $filters->parentIds);
        $categoryIds = $this->scope->resolveCategoryIdsFromParents($parentsCsv, $depth);

        if ($categoryIds === []) {
            // Force empty result set without raw SQL.
            $query->where(['msProduct.id' => 0]);

            return;
        }

        $this->scope->applyProductCategoryScope($query, $categoryIds);
    }

    private function applyDataFilters(xPDOQuery $query, ProductCatalogFilterSpec $filters): void
    {
        if ($filters->priceMin !== null) {
            $query->where(['Data.price:>=' => $filters->priceMin]);
        }
        if ($filters->priceMax !== null) {
            $query->where(['Data.price:<=' => $filters->priceMax]);
        }

        if ($filters->inStock) {
            $query->where(['Data.stock:>' => 0]);
        }
        if ($filters->stockMin !== null) {
            $query->where(['Data.stock:>=' => $filters->stockMin]);
        }

        if ($filters->vendorIds !== []) {
            $query->where(['Data.vendor_id:IN' => $filters->vendorIds]);
        }

        if ($filters->flagNew) {
            $query->where(['Data.new' => 1]);
        }
        if ($filters->flagPopular) {
            $query->where(['Data.popular' => 1]);
        }
        if ($filters->flagFavorite) {
            $query->where(['Data.favorite' => 1]);
        }
    }

    private function applyOptionFilters(
        xPDOQuery $query,
        ProductCatalogFilterSpec $filters,
        bool $dedupeRows,
    ): void {
        if ($filters->options === []) {
            return;
        }

        $this->assertKnownOptionKeys(array_keys($filters->options));

        $index = 0;
        foreach ($filters->options as $key => $values) {
            $alias = 'OptFilter' . $index++;

            // Key is validated as [a-zA-Z0-9_]+ and exists in msOption.
            $query->innerJoin(
                msProductOption::class,
                $alias,
                "`{$alias}`.product_id = Data.id AND `{$alias}`.`key` = " . $this->modx->quote($key)
            );
            $query->where(["{$alias}.value:IN" => $values]);
        }

        // Multi-value option rows duplicate product rows on list pages.
        // Count uses COUNT(DISTINCT) without GROUP BY (GROUP BY would break fetchColumn total).
        if ($dedupeRows) {
            $query->groupby('msProduct.id');
        }
    }

    /**
     * @param list<string> $keys
     */
    public function assertKnownOptionKeys(array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $c = $this->modx->newQuery(msOption::class);
        $c->where(['key:IN' => $keys]);
        $c->select('key');

        if (!$c->prepare() || !$c->stmt->execute()) {
            throw new \RuntimeException('Failed to validate catalog option keys');
        }

        $found = array_map('strval', $c->stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);
        if (array_diff($keys, $found) !== []) {
            throw new ProductCatalogFilterException('ms3_err_catalog_option_unknown');
        }
    }
}
