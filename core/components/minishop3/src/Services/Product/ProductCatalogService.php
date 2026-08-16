<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\Catalog\CatalogQuery;
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

        return $result;
    }

    public static function toBool(mixed $value): bool
    {
        return CatalogQuery::toBool($value);
    }

    /**
     * Single published product by ID (same visibility rules as list).
     *
     * @param array<string, mixed> $params Optional context override
     * @return array<string, mixed>|null
     */
    public function getById(int $productId, array $params = []): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        $criteria = ['id' => $productId];
        $context = $this->resolveContext($params);
        if ($context !== '') {
            $criteria['context_key'] = $context;
        }

        /** @var msProduct|null $product */
        $product = $this->modx->getObject(msProduct::class, $this->publicCriteria($criteria));

        if (!$product) {
            return null;
        }

        $options = self::stripOptionMetadata(
            $this->optionService()->loadOptionsForProduct($productId, false)
        );

        return $this->formatProduct($product, true, $options);
    }

    /**
     * Paginated product list.
     *
     * Supported filters in $params:
     * - parent|category: primary parent resource id (not msCategoryMember)
     * - limit, offset | page
     * - sort, dir (ASC|DESC)
     * - query: pagetitle / article search
     * - context: MODX context key (default: current)
     * - include_options: 0|1 (default 0 for list)
     * - include_content: 0|1 (default 0 for list)
     *
     * @param array<string, mixed> $params
     * @return array{items: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function getList(array $params): array
    {
        $limit = self::resolveLimit($params);
        $offset = self::resolveOffset($params, $limit);
        $includeOptions = self::toBool($params['include_options'] ?? false);
        $includeContent = self::toBool($params['include_content'] ?? false);

        $total = $this->countList($params);

        $listQuery = $this->buildListQuery($params);
        $this->applySort($listQuery, $params);
        $listQuery->limit($limit, $offset);

        /** @var list<msProduct> $products */
        $products = $this->modx->getCollection(msProduct::class, $listQuery) ?: [];

        $optionsByProduct = [];
        if ($includeOptions && $products !== []) {
            $ids = array_map(static fn (msProduct $p) => (int) $p->get('id'), array_values($products));
            $optionsByProduct = $this->loadOptionsForProducts($ids);
        }

        $items = [];
        foreach ($products as $product) {
            $productId = (int) $product->get('id');
            $options = $includeOptions ? ($optionsByProduct[$productId] ?? []) : null;
            $items[] = $this->formatProduct($product, $includeContent, $options);
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
    private function countList(array $params): int
    {
        $countQuery = $this->buildListQuery($params);
        $countQuery->select('COUNT(DISTINCT msProduct.id)');
        if (!$countQuery->prepare() || !$countQuery->stmt->execute()) {
            return 0;
        }

        return (int) $countQuery->stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildListQuery(array $params): xPDOQuery
    {
        $c = $this->modx->newQuery(msProduct::class);
        $c->innerJoin(msProductData::class, 'Data', 'msProduct.id = Data.id');
        $c->where($this->publicCriteria());

        $context = $this->resolveContext($params);
        if ($context !== '') {
            $c->where(['msProduct.context_key' => $context]);
        }

        $parent = (int) ($params['parent'] ?? $params['category'] ?? 0);
        if ($parent > 0) {
            $c->where(['msProduct.parent' => $parent]);
        }

        $query = trim((string) ($params['query'] ?? ''));
        if ($query !== '') {
            $c->where([
                'msProduct.pagetitle:LIKE' => '%' . $query . '%',
                'OR:Data.article:LIKE' => '%' . $query . '%',
            ]);
        }

        return $c;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function applySort(xPDOQuery $query, array $params): void
    {
        [$sortField, $dir] = self::resolveSort($params);
        $query->sortby($sortField, $dir);
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
     * @return array<string, mixed>
     */
    private function formatProduct(
        msProduct $product,
        bool $includeContent,
        ?array $options = null,
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

        $modified = $product->modifyFields($payload);
        if (is_array($modified)) {
            $payload = $modified;
        }

        return self::whitelistPublicPayload($payload, $includeContent, $options !== null);
    }
}
