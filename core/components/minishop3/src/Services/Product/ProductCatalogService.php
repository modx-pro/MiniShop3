<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msCategoryMember;
use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use MiniShop3\Services\Category\CategoryProductMenuindexService;
use MiniShop3\Services\Category\CategoryProductScopeService;
use MiniShop3\Services\Option\OptionService;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Public catalog reads for Web API (headless / SPA).
 *
 * Always scopes to published, non-deleted, non-hidemenu msProduct rows
 * in the requested (or current) context. Response fields are allowlisted
 * even after msOnGetProductFields plugins run.
 */
class ProductCatalogService
{
    /** @var list<string> */
    private const RESOURCE_FIELDS = [
        'id',
        'pagetitle',
        'longtitle',
        'description',
        'introtext',
        'alias',
        'uri',
        'parent',
        'menuindex',
        'context_key',
        'publishedon',
        'createdon',
        'editedon',
    ];

    /** @var list<string> */
    private const DATA_FIELDS = [
        'article',
        'price',
        'old_price',
        'stock',
        'weight',
        'image',
        'thumb',
        'vendor_id',
        'made_in',
        'new',
        'popular',
        'favorite',
    ];

    /** @var array<string, string> sort request key => SQL expression */
    private const SORT_MAP = [
        'id' => 'msProduct.id',
        'pagetitle' => 'msProduct.pagetitle',
        'menuindex' => 'msProduct.menuindex',
        'createdon' => 'msProduct.createdon',
        'publishedon' => 'msProduct.publishedon',
        'price' => 'Data.price',
        'article' => 'Data.article',
    ];

    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * Clamp list page size (default 20, max 100).
     *
     * @param array<string, mixed> $params
     */
    public static function resolveLimit(array $params): int
    {
        return CatalogQuery::resolveLimit($params);
    }

    /**
     * Offset from explicit offset or 1-based page.
     *
     * @param array<string, mixed> $params
     */
    public static function resolveOffset(array $params, int $limit): int
    {
        return CatalogQuery::resolveOffset($params, $limit);
    }

    /**
     * Whitelisted sort column + ASC|DESC.
     *
     * @param array<string, mixed> $params
     * @return array{0: string, 1: string} SQL field, direction
     */
    public static function resolveSort(array $params): array
    {
        return CatalogQuery::resolveSort($params, self::SORT_MAP);
    }

    /**
     * Keep option values only; drop dotted metadata keys (color.caption, …).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function stripOptionMetadata(array $options): array
    {
        $result = [];
        foreach ($options as $key => $value) {
            if (!is_string($key) || str_contains($key, '.')) {
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Drop keys outside the public catalog contract (after plugin modifyFields).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function whitelistPublicPayload(
        array $payload,
        bool $includeContent,
        bool $includeOptions,
        bool $includeImages = false,
    ): array {
        $allowed = array_merge(self::RESOURCE_FIELDS, self::DATA_FIELDS);
        if ($includeContent) {
            $allowed[] = 'content';
        }

        $result = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $payload)) {
                $result[$field] = $payload[$field];
            }
        }

        if ($includeOptions && isset($payload['options']) && is_array($payload['options'])) {
            $result['options'] = self::stripOptionMetadata($payload['options']);
        }

        if ($includeImages && is_array($payload['images'] ?? null)) {
            $result['images'] = ProductGalleryPublicSerializer::whitelistItems($payload['images']);
        }

        return $result;
    }

    public static function toBool(mixed $value): bool
    {
        return CatalogQuery::toBool($value);
    }

    /**
     * Single published product by ID (same visibility rules as list).
     *
     * Query: context, include_images (0|1, default 0).
     *
     * @param array<string, mixed> $params Optional context override
     * @return array<string, mixed>|null
     */
    public function getById(int $productId, array $params = []): ?array
    {
        $product = $this->findVisibleProduct($productId, $params);
        if ($product === null) {
            return null;
        }

        $options = self::stripOptionMetadata(
            $this->optionService()->loadOptionsForProduct($productId, false)
        );

        $includeImages = self::toBool($params['include_images'] ?? false);
        $images = $includeImages ? $this->loadImagesForProduct($product) : null;

        return $this->formatProduct($product, true, $options, $images);
    }

    /**
     * Gallery only: same visibility as get. Always returns images[] (may be empty).
     *
     * @param array<string, mixed> $params
     * @return array{images: list<array<string, mixed>>}|null
     */
    public function getPublicImages(int $productId, array $params = []): ?array
    {
        $product = $this->findVisibleProduct($productId, $params);
        if ($product === null) {
            return null;
        }

        return ['images' => $this->loadImagesForProduct($product)];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function findVisibleProduct(int $productId, array $params): ?msProduct
    {
        if ($productId <= 0) {
            return null;
        }

        $c = $this->modx->newQuery(msProduct::class);
        $c->where($this->publicCriteria(['id' => $productId]));
        $this->applyProductCatalogScope($c, $params);

        /** @var msProduct|null $product */
        $product = $this->modx->getObject(msProduct::class, $c);

        return $product ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadImagesForProduct(msProduct $product): array
    {
        $data = $product->loadData();
        $previewFileId = $data
            ? $this->imageService()->resolvePreviewFileId($data)
            : 0;

        return $this->gallery()->loadForProduct(
            (int) $product->get('id'),
            (string) $product->get('pagetitle'),
            $previewFileId,
        );
    }

    /**
     * @param list<msProduct> $products
     * @return array<int, list<array<string, mixed>>>
     */
    private function loadImagesForProducts(array $products): array
    {
        $meta = [];
        foreach ($products as $product) {
            $id = (int) $product->get('id');
            if ($id <= 0) {
                continue;
            }
            $data = $product->loadData();
            $meta[$id] = [
                'pagetitle' => (string) $product->get('pagetitle'),
                'preview_file_id' => $data ? (int) $data->get('preview_file_id') : 0,
            ];
        }

        return $this->gallery()->loadForProducts($meta, ProductGalleryPublicSerializer::MAX_IMAGES_LIST);
    }

    /**
     * Paginated product list.
     *
     * Supported filters in $params:
     * - parent|category: primary parent resource id (BC; ignored when `parents` set)
     * - parents: CSV/array of category IDs (OR; + msCategoryMember via scope)
     * - nested: 0|1 expand category tree when using parents
     * - price_min / price_max: filter on stored Data.price (not plugin getPrice())
     * - in_stock, stock_min, vendor_id, new, popular, favorite
     * - options: JSON object or bracket map (AND between keys, OR within key)
     * - limit, offset | page, sort, dir, query, context
     * - include_options, include_content, include_images (default 0; cap 10 files / product)
     *
     * @param array<string, mixed> $params
     * @return array{items: list<array<string, mixed>>, total: int, limit: int, offset: int}
     *
     * @throws ProductCatalogFilterException
     */
    public function getList(array $params): array
    {
        $filters = ProductCatalogFilterParser::parse($params);

        $limit = self::resolveLimit($params);
        $offset = self::resolveOffset($params, $limit);
        $includeOptions = self::toBool($params['include_options'] ?? false);
        $includeContent = self::toBool($params['include_content'] ?? false);
        $includeImages = self::toBool($params['include_images'] ?? false);

        $total = $this->countList($params, $filters);

        $listQuery = $this->buildListQuery($params, $filters);
        $this->applyListSelect($listQuery, $includeContent);
        $this->applySort($listQuery, $params, $filters);
        $listQuery->limit($limit, $offset);

        /** @var array<int|string, msProduct> $products */
        $products = $this->modx->getCollection(msProduct::class, $listQuery) ?: [];
        $productList = array_values($products);
        $ids = $this->prefetchAndAttachProductData($productList);

        $optionsByProduct = ($includeOptions && $ids !== [])
            ? $this->loadOptionsForProducts($ids)
            : [];

        $galleries = ($includeImages && $ids !== [])
            ? $this->loadImagesForProducts($productList)
            : [];

        $items = [];
        foreach ($productList as $product) {
            $productId = (int) $product->get('id');
            $options = $includeOptions ? ($optionsByProduct[$productId] ?? []) : null;
            $images = $includeImages ? ($galleries[$productId] ?? []) : null;
            $items[] = $this->formatProduct($product, $includeContent, $options, $images);
        }

        return [
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function publicCriteria(array $extra = []): array
    {
        return array_merge([
            'class_key' => msProduct::class,
            'published' => 1,
            'deleted' => 0,
            'hidemenu' => 0,
        ], $extra);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveContext(array $params): string
    {
        return CatalogQuery::resolveContext(
            $params,
            (string) ($this->modx->context->key ?? 'web'),
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function countList(array $params, ProductCatalogFilterSpec $filters): int
    {
        $countQuery = $this->buildListQuery($params, $filters, false);
        // Option JOINs can duplicate product rows; 1:1 Data join does not.
        $countQuery->select(
            $filters->options !== []
                ? 'COUNT(DISTINCT msProduct.id)'
                : 'COUNT(msProduct.id)'
        );
        if (!$countQuery->prepare() || !$countQuery->stmt->execute()) {
            return 0;
        }

        return (int) $countQuery->stmt->fetchColumn();
    }

    /**
     * Storefront-scoped product query (visibility + context + list filters).
     * Shared with product/filters facets (#565).
     *
     * @param array<string, mixed> $params
     * @param bool $dedupeRows GROUP BY for list pages; false for COUNT(DISTINCT) / aggregates
     */
    public function buildScopedListQuery(
        array $params,
        ProductCatalogFilterSpec $filters,
        bool $dedupeRows = true,
    ): xPDOQuery {
        return $this->buildListQuery($params, $filters, $dedupeRows);
    }

    /**
     * @param array<string, mixed> $params
     * @param bool $dedupeRows GROUP BY for list pages; false for COUNT(DISTINCT) queries
     */
    private function buildListQuery(
        array $params,
        ProductCatalogFilterSpec $filters,
        bool $dedupeRows = true,
    ): xPDOQuery {
        $c = $this->modx->newQuery(msProduct::class);
        $c->innerJoin(msProductData::class, 'Data', 'msProduct.id = Data.id');
        $c->where($this->publicCriteria());

        if (!$filters->hasParents()) {
            $parent = (int) ($params['parent'] ?? $params['category'] ?? 0);
            if ($parent > 0) {
                $c->where(['msProduct.parent' => $parent]);
            }
        }

        $query = trim((string) ($params['query'] ?? ''));
        if ($query !== '') {
            $c->where([
                'msProduct.pagetitle:LIKE' => '%' . $query . '%',
                'OR:Data.article:LIKE' => '%' . $query . '%',
            ]);
        }

        $this->filterApplier()->apply($c, $filters, $dedupeRows);
        $this->applyProductCatalogScope($c, $params);

        return $c;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function applyProductCatalogScope(xPDOQuery $query, array $params): void
    {
        $context = $this->resolveContext($params);
        if ($context !== '') {
            $query->where(['msProduct.context_key' => $context]);
        }
        $this->resourceGroupVisibility()->apply($query, 'msProduct', $context);
    }

    private function resourceGroupVisibility(): CatalogResourceGroupVisibility
    {
        return new CatalogResourceGroupVisibility($this->modx);
    }

    private function filterApplier(): ProductCatalogFilterApplier
    {
        /** @var CategoryProductScopeService $scope */
        $scope = $this->modx->services->get('ms3_category_product_scope');

        return new ProductCatalogFilterApplier($this->modx, $scope);
    }

    /**
     * Limit selected resource columns; skip content blob on PLP when not requested.
     * Same pattern as ms3_products snippet.
     */
    private function applyListSelect(xPDOQuery $query, bool $includeContent): void
    {
        $query->select(
            $includeContent
                ? $this->modx->getSelectColumns(msProduct::class, 'msProduct')
                : $this->modx->getSelectColumns(msProduct::class, 'msProduct', '', ['content'], true)
        );
    }

    /**
     * Batch-load msProductData and attach via addOne so loadData() skips getOne N+1.
     *
     * List query already JOINs Data for WHERE/ORDER only (no related hydrate from that JOIN).
     * One IN-query here is O(1) vs L× getOne; total SQL ≈ count + list + data (+ options).
     *
     * @param list<msProduct> $products
     * @return list<int>
     */
    private function prefetchAndAttachProductData(array $products): array
    {
        $byId = [];
        foreach ($products as $product) {
            $id = (int) $product->get('id');
            if ($id > 0) {
                $byId[$id] = $product;
            }
        }

        if ($byId === []) {
            return [];
        }

        $ids = array_keys($byId);
        $c = $this->modx->newQuery(msProductData::class);
        $c->where(['id:IN' => $ids]);

        /** @var msProductData $data */
        foreach ($this->modx->getCollection(msProductData::class, $c) ?: [] as $data) {
            $id = (int) $data->get('id');
            if (!isset($byId[$id])) {
                continue;
            }
            // addOne requires a by-ref argument (xPDO signature).
            $attached = $data;
            $byId[$id]->addOne($attached, 'Data');
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function applySort(xPDOQuery $query, array $params, ProductCatalogFilterSpec $filters): void
    {
        $sortKey = strtolower(trim((string) ($params['sort'] ?? 'menuindex')));
        if ($sortKey === 'menuindex') {
            $categoryIds = $this->resolveMenuindexCategoryIds($params, $filters);
            if ($categoryIds !== []) {
                $this->applyEffectiveMenuindexSort($query, $categoryIds, $params, $filters);

                return;
            }
        }

        [$sortField, $dir] = self::resolveSort($params);
        $query->sortby($sortField, $dir);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<int>
     */
    public function resolveMenuindexCategoryIds(array $params, ProductCatalogFilterSpec $filters): array
    {
        if ($filters->hasParents()) {
            $depth = $filters->nested ? ProductCatalogFilterApplier::NESTED_DEPTH : 0;
            $parentsCsv = implode(',', $filters->parentIds);

            return $this->categoryScopeService()->resolveCategoryIdsFromParents($parentsCsv, $depth);
        }

        $parent = (int) ($params['parent'] ?? $params['category'] ?? 0);
        if ($parent > 0) {
            return [$parent];
        }

        return [];
    }

    /**
     * @param list<int> $categoryIds
     * @param array<string, mixed> $params
     */
    private function applyEffectiveMenuindexSort(
        xPDOQuery $query,
        array $categoryIds,
        array $params,
        ProductCatalogFilterSpec $filters,
    ): void {
        $memberAlias = CategoryProductMenuindexService::MEMBER_JOIN_ALIAS;
        $sortSql = CategoryProductMenuindexService::applyMemberJoin($query, $categoryIds);

        [, $dir] = self::resolveSort($params);

        if (count($categoryIds) > 1 || $filters->options !== []) {
            $query->groupby('msProduct.id');
        }

        $query->sortby($sortSql, $dir);
    }

    private function categoryScopeService(): CategoryProductScopeService
    {
        /** @var CategoryProductScopeService $scope */
        $scope = $this->modx->services->get('ms3_category_product_scope');

        return $scope;
    }

    /**
     * @param list<int> $productIds
     * @return array<int, array<string, mixed>>
     */
    private function loadOptionsForProducts(array $productIds): array
    {
        $raw = $this->optionService()->loadOptionsForProducts($productIds, false);

        $cleaned = [];
        foreach ($raw as $productId => $options) {
            $cleaned[(int) $productId] = self::stripOptionMetadata($options);
        }

        return $cleaned;
    }

    private function optionService(): OptionService
    {
        /** @var OptionService $service */
        $service = $this->modx->services->get('ms3_option_service');

        return $service;
    }

    /**
     * @param array<string, mixed>|null $options null = omit options key; array = include
     * @param list<array<string, mixed>>|null $images null = omit images key; array = include
     * @return array<string, mixed>
     */
    private function formatProduct(
        msProduct $product,
        bool $includeContent,
        ?array $options = null,
        ?array $images = null,
    ): array {
        $data = $product->loadData();
        $payload = [];

        foreach (self::RESOURCE_FIELDS as $field) {
            $payload[$field] = $product->get($field);
        }

        $dataValues = [];
        if ($data) {
            foreach (self::DATA_FIELDS as $field) {
                $dataValues[$field] = $data->get($field);
            }
        } else {
            foreach (self::DATA_FIELDS as $field) {
                $dataValues[$field] = null;
            }
        }

        $originalPrice = (float) ($dataValues['price'] ?? 0);
        $price = (float) $product->getPrice($dataValues);
        $dataValues['price'] = $price;
        if ($price < $originalPrice && empty($dataValues['old_price'])) {
            $dataValues['old_price'] = $originalPrice;
        }
        $dataValues['weight'] = (float) $product->getWeight($dataValues);

        $payload = array_merge($payload, $dataValues);

        if ($includeContent) {
            $payload['content'] = $product->get('content');
        }

        if ($options !== null) {
            $payload['options'] = $options;
        }

        if ($images !== null) {
            $payload['images'] = $images;
        }

        $modified = $product->modifyFields($payload);
        if (is_array($modified)) {
            $payload = $modified;
        }

        return self::whitelistPublicPayload(
            $payload,
            $includeContent,
            $options !== null,
            $images !== null,
        );
    }

    private function gallery(): ProductGalleryPublicService
    {
        /** @var ProductGalleryPublicService $service */
        $service = $this->modx->services->get('ms3_product_gallery_public');

        return $service;
    }

    private function imageService(): ProductImageService
    {
        /** @var ProductImageService $service */
        $service = $this->modx->services->get('ms3_product_image');

        return $service;
    }
}
