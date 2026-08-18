<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msProductOption;
use MiniShop3\Model\msVendor;
use MiniShop3\Services\Category\CategoryProductScopeService;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;
use xPDO\xPDO;

/**
 * Public catalog facets for GET /api/v1/product/filters (#565).
 *
 * Soft-count semantics:
 * - option buckets for key K ignore selected options.K (other filters stay);
 * - price min/max ignore price_min/price_max (slider bounds);
 * - vendor buckets ignore vendor_id.
 *
 * Visibility matches product/list (published / not deleted / not hidemenu / context).
 * nested expands category tree only with parents= (same as product/list).
 */
final class ProductFacetService
{
    public const MAX_FACET_KEYS = 20;
    public const MAX_VALUES_PER_KEY = 50;
    public const MAX_VENDOR_BUCKETS = 50;
    public const CACHE_TTL_SECONDS = 120;

    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @return array{
     *     price?: array{min: float|null, max: float|null},
     *     options: array<string, list<array{value: string, count: int}>>,
     *     vendors?: list<array{id: int, name: string, count: int}>
     * }
     *
     * @throws ProductCatalogFilterException
     */
    public function getFilters(array $params): array
    {
        $filters = ProductCatalogFilterParser::parse($params);
        $includePrice = ProductCatalogService::toBool($params['include_price'] ?? true);
        $includeVendors = ProductCatalogService::toBool($params['include_vendors'] ?? false);
        [$keys, $keysOk] = $this->resolveFacetKeys($params, $filters);

        $cacheKey = $this->buildCacheKey($params, $filters, $keys, $includePrice, $includeVendors);
        $cached = $this->cacheGet($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = [
            'options' => [],
        ];
        $cacheable = $keysOk;

        if ($includePrice) {
            [$result['price'], $priceOk] = $this->aggregatePriceBounds($params, $filters);
            $cacheable = $cacheable && $priceOk;
        }

        foreach ($keys as $key) {
            [$result['options'][$key], $optionOk] = $this->aggregateOptionBuckets($params, $filters, $key);
            $cacheable = $cacheable && $optionOk;
        }

        if ($includeVendors) {
            [$result['vendors'], $vendorOk] = $this->aggregateVendorBuckets($params, $filters);
            $cacheable = $cacheable && $vendorOk;
        }

        // Do not cache empty/partial payloads produced by SQL failures.
        if ($cacheable) {
            $this->cacheSet($cacheKey, $result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: list<string>, 1: bool} keys, SQL ok
     */
    private function resolveFacetKeys(array $params, ProductCatalogFilterSpec $filters): array
    {
        // Explicit keys= (including empty) must not silently fall back to auto-discovery.
        if (array_key_exists('keys', $params) && $params['keys'] !== null) {
            $keys = $this->parseRequestedKeys($params['keys']);
            $this->filterApplier()->assertKnownOptionKeys($keys);

            return [$keys, true];
        }

        [$fromCategory, $categoryOk] = $this->keysFromCategoryOptions($params, $filters);
        if ($fromCategory !== []) {
            return [$fromCategory, $categoryOk];
        }

        return $this->keysFromAllOptions();
    }

    /**
     * @return list<string>
     */
    private function parseRequestedKeys(mixed $raw): array
    {
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        $keys = [];
        foreach ($parts as $part) {
            if (is_array($part)) {
                throw new ProductCatalogFilterException('ms3_err_catalog_facet_keys_invalid');
            }
            $key = trim((string) $part);
            if ($key === '') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new ProductCatalogFilterException('ms3_err_catalog_facet_keys_invalid');
            }
            $keys[] = $key;
        }

        $keys = array_values(array_unique($keys));
        if ($keys === []) {
            throw new ProductCatalogFilterException('ms3_err_catalog_facet_keys_invalid');
        }
        if (count($keys) > self::MAX_FACET_KEYS) {
            throw new ProductCatalogFilterException('ms3_err_catalog_facet_keys_limit');
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: list<string>, 1: bool}
     */
    private function keysFromCategoryOptions(array $params, ProductCatalogFilterSpec $filters): array
    {
        $categoryIds = $this->resolveScopeCategoryIds($params, $filters);
        if ($categoryIds === []) {
            return [[], true];
        }

        $c = $this->modx->newQuery(msCategoryOption::class);
        $c->innerJoin(msOption::class, 'Option', 'Option.id = msCategoryOption.option_id');
        $c->where([
            'msCategoryOption.category_id:IN' => $categoryIds,
            'msCategoryOption.active' => 1,
        ]);
        $c->select('Option.key');
        $c->groupby('Option.key');
        $c->sortby('MIN(msCategoryOption.position)', 'ASC');
        $c->limit(self::MAX_FACET_KEYS);

        return $this->fetchQueryKeys($c);
    }

    /**
     * @return array{0: list<string>, 1: bool}
     */
    private function keysFromAllOptions(): array
    {
        $c = $this->modx->newQuery(msOption::class);
        $c->select('key');
        $c->sortby('id', 'ASC');
        $c->limit(self::MAX_FACET_KEYS);

        return $this->fetchQueryKeys($c);
    }

    /**
     * @return array{0: list<string>, 1: bool}
     */
    private function fetchQueryKeys(xPDOQuery $query): array
    {
        if (!$query->prepare() || !$query->stmt->execute()) {
            return [[], false];
        }

        $keys = array_values(array_filter(
            array_map('strval', $query->stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []),
            static fn (string $key): bool => $key !== ''
        ));

        return [$keys, true];
    }

    /**
     * Category IDs for auto facet keys. nested expands only when parents= is set
     * (parity with product/list: parent|category alone is leaf-only).
     *
     * @param array<string, mixed> $params
     * @return list<int>
     */
    private function resolveScopeCategoryIds(array $params, ProductCatalogFilterSpec $filters): array
    {
        $parentIds = $filters->parentIds;
        if ($parentIds === []) {
            $parent = (int) ($params['parent'] ?? $params['category'] ?? 0);
            if ($parent > 0) {
                $parentIds = [$parent];
            }
        }

        if ($parentIds === []) {
            return [];
        }

        $depth = ($filters->hasParents() && $filters->nested)
            ? ProductCatalogFilterApplier::NESTED_DEPTH
            : 0;

        return $this->scope()->resolveCategoryIdsFromParents(implode(',', $parentIds), $depth);
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: array{min: float|null, max: float|null}, 1: bool}
     */
    private function aggregatePriceBounds(array $params, ProductCatalogFilterSpec $filters): array
    {
        $query = $this->catalog()->buildScopedListQuery(
            $params,
            $filters->withoutPriceBounds(),
            false,
        );
        $query->select('MIN(Data.price) AS price_min, MAX(Data.price) AS price_max');

        if (!$query->prepare() || !$query->stmt->execute()) {
            return [['min' => null, 'max' => null], false];
        }

        $row = $query->stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            [
                'min' => $this->nullableFloat($row['price_min'] ?? null),
                'max' => $this->nullableFloat($row['price_max'] ?? null),
            ],
            true,
        ];
    }

    /**
     * Soft option facet via scoped product query + JOIN (no PHP ID materialization).
     *
     * @param array<string, mixed> $params
     * @return array{0: list<array{value: string, count: int}>, 1: bool}
     */
    private function aggregateOptionBuckets(
        array $params,
        ProductCatalogFilterSpec $filters,
        string $key,
    ): array {
        $query = $this->catalog()->buildScopedListQuery(
            $params,
            $filters->withoutOptionKey($key),
            false,
        );
        $query->innerJoin(
            msProductOption::class,
            'FacetOpt',
            'FacetOpt.product_id = Data.id AND FacetOpt.`key` = ' . $this->modx->quote($key)
        );
        $query->select('FacetOpt.value AS value, COUNT(DISTINCT msProduct.id) AS cnt');
        $query->groupby('FacetOpt.value');
        $query->sortby('cnt', 'DESC');
        $query->limit(self::MAX_VALUES_PER_KEY);

        if (!$query->prepare() || !$query->stmt->execute()) {
            return [[], false];
        }

        $buckets = [];
        foreach ($query->stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $buckets[] = [
                'value' => $value,
                'count' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return [$buckets, true];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: list<array{id: int, name: string, count: int}>, 1: bool}
     */
    private function aggregateVendorBuckets(array $params, ProductCatalogFilterSpec $filters): array
    {
        $query = $this->catalog()->buildScopedListQuery(
            $params,
            $filters->withoutVendors(),
            false,
        );
        $query->where(['Data.vendor_id:>' => 0]);
        $query->select('Data.vendor_id AS vendor_id, COUNT(DISTINCT msProduct.id) AS cnt');
        $query->groupby('Data.vendor_id');
        $query->sortby('cnt', 'DESC');
        $query->limit(self::MAX_VENDOR_BUCKETS);

        if (!$query->prepare() || !$query->stmt->execute()) {
            return [[], false];
        }

        $rows = $query->stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return [[], true];
        }

        $ids = [];
        $counts = [];
        foreach ($rows as $row) {
            $id = (int) ($row['vendor_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $ids[] = $id;
            $counts[$id] = (int) ($row['cnt'] ?? 0);
        }

        if ($ids === []) {
            return [[], true];
        }

        [$names, $namesOk] = $this->loadVendorNames($ids);
        $buckets = [];
        foreach ($ids as $id) {
            $buckets[] = [
                'id' => $id,
                'name' => $names[$id] ?? '',
                'count' => $counts[$id] ?? 0,
            ];
        }

        return [$buckets, $namesOk];
    }

    /**
     * @param list<int> $ids
     * @return array{0: array<int, string>, 1: bool}
     */
    private function loadVendorNames(array $ids): array
    {
        $c = $this->modx->newQuery(msVendor::class);
        $c->where(['id:IN' => $ids]);
        $c->select('id, name');

        if (!$c->prepare() || !$c->stmt->execute()) {
            return [[], false];
        }

        $names = [];
        foreach ($c->stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $names[(int) $row['id']] = (string) ($row['name'] ?? '');
        }

        return [$names, true];
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * @param array<string, mixed> $params
     * @param list<string> $keys
     */
    private function buildCacheKey(
        array $params,
        ProductCatalogFilterSpec $filters,
        array $keys,
        bool $includePrice,
        bool $includeVendors,
    ): string {
        $payload = [
            'context' => (string) ($params['context'] ?? ''),
            'parent' => (int) ($params['parent'] ?? $params['category'] ?? 0),
            'parents' => $filters->parentIds,
            'nested' => $filters->nested,
            'price_min' => $filters->priceMin,
            'price_max' => $filters->priceMax,
            'in_stock' => $filters->inStock,
            'stock_min' => $filters->stockMin,
            'vendor_id' => $filters->vendorIds,
            'new' => $filters->flagNew,
            'popular' => $filters->flagPopular,
            'favorite' => $filters->flagFavorite,
            'options' => $filters->options,
            'query' => trim((string) ($params['query'] ?? '')),
            'keys' => $keys,
            'include_price' => $includePrice,
            'include_vendors' => $includeVendors,
        ];

        return 'facets_' . hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cacheGet(string $key): ?array
    {
        $cacheManager = $this->modx->cacheManager;
        if (!is_object($cacheManager)) {
            return null;
        }

        $value = $cacheManager->get($key, $this->cacheOptions());

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function cacheSet(string $key, array $value): void
    {
        $cacheManager = $this->modx->cacheManager;
        if (!is_object($cacheManager)) {
            return;
        }

        $cacheManager->set($key, $value, self::CACHE_TTL_SECONDS, $this->cacheOptions());
    }

    /**
     * @return array<string, string>
     */
    private function cacheOptions(): array
    {
        return [xPDO::OPT_CACHE_KEY => 'ms3/facets'];
    }

    private function catalog(): ProductCatalogService
    {
        /** @var ProductCatalogService $service */
        $service = $this->modx->services->get('ms3_product_catalog');

        return $service;
    }

    private function scope(): CategoryProductScopeService
    {
        /** @var CategoryProductScopeService $service */
        $service = $this->modx->services->get('ms3_category_product_scope');

        return $service;
    }

    private function filterApplier(): ProductCatalogFilterApplier
    {
        return new ProductCatalogFilterApplier($this->modx, $this->scope());
    }
}
