<?php

declare(strict_types=1);

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Services\Product\ProductCatalogService;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Public category catalog for Web API (headless / SPA).
 *
 * Scopes to published, non-deleted msCategory rows in the requested context.
 * Navigation lists/trees hide hidemenu=1 unless include_hidden is set.
 */
class CategoryCatalogService
{
    private const DEFAULT_DEPTH = 5;
    private const MAX_DEPTH = 10;

    /** @var list<string> */
    private const RESOURCE_FIELDS = [
        'id',
        'pagetitle',
        'longtitle',
        'menutitle',
        'description',
        'introtext',
        'alias',
        'uri',
        'parent',
        'menuindex',
        'hidemenu',
        'context_key',
        'publishedon',
        'createdon',
        'editedon',
    ];

    /** @var list<string> */
    private const BREADCRUMB_FIELDS = [
        'id',
        'pagetitle',
        'menutitle',
        'alias',
        'uri',
        'parent',
    ];

    /** @var list<string> */
    private const INT_FIELDS = ['id', 'parent', 'menuindex'];

    /** @var array<string, string> */
    private const SORT_MAP = [
        'id' => 'msCategory.id',
        'pagetitle' => 'msCategory.pagetitle',
        'menuindex' => 'msCategory.menuindex',
    ];

    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function resolveDepth(array $params): int
    {
        $depth = (int) ($params['depth'] ?? self::DEFAULT_DEPTH);
        if ($depth < 1) {
            return self::DEFAULT_DEPTH;
        }

        return min($depth, self::MAX_DEPTH);
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: string}
     */
    public static function resolveSort(array $params): array
    {
        $sortKey = strtolower(trim((string) ($params['sort'] ?? 'menuindex')));
        $sortField = self::SORT_MAP[$sortKey] ?? self::SORT_MAP['menuindex'];

        $dir = strtoupper(trim((string) ($params['dir'] ?? $params['sortdir'] ?? 'ASC')));
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }

        return [$sortField, $dir];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function whitelistPublicPayload(array $payload, bool $includeContent): array
    {
        $allowed = self::RESOURCE_FIELDS;
        if ($includeContent) {
            $allowed[] = 'content';
        }

        $result = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $payload)) {
                $result[$field] = $payload[$field];
            }
        }

        return $result;
    }

    /**
     * Build nested tree from a flat id→node map (nodes already filtered for visibility).
     *
     * @param array<int, array<string, mixed>> $byId
     * @param array<int, list<int>> $childrenByParent
     * @return list<array<string, mixed>>
     */
    public static function buildTreeNodes(
        array $byId,
        array $childrenByParent,
        int $parentId,
        int $depth,
    ): array {
        if ($depth < 1) {
            return [];
        }

        $items = [];
        foreach ($childrenByParent[$parentId] ?? [] as $childId) {
            if (!isset($byId[$childId])) {
                continue;
            }
            $node = $byId[$childId];
            $node['children'] = self::buildTreeNodes($byId, $childrenByParent, $childId, $depth - 1);
            $items[] = $node;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function getById(int $categoryId, array $params = []): ?array
    {
        if ($categoryId <= 0) {
            return null;
        }

        $includeHidden = ProductCatalogService::toBool($params['include_hidden'] ?? false);
        $category = $this->findVisibleCategory($categoryId, $params, $includeHidden);
        if ($category === null) {
            return null;
        }

        $includeContent = ProductCatalogService::toBool($params['include_content'] ?? false);
        $payload = $this->formatCategory($category, $includeContent);

        $includeBreadcrumbs = !isset($params['include_breadcrumbs'])
            || ProductCatalogService::toBool($params['include_breadcrumbs']);
        if ($includeBreadcrumbs) {
            $payload['breadcrumbs'] = $this->buildBreadcrumbs($category, $params, $includeHidden);
        }

        if (ProductCatalogService::toBool($params['include_children'] ?? false)) {
            $payload['children'] = $this->listDirectChildrenPayloads($categoryId, $params, $includeHidden, false);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{items: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function getList(array $params): array
    {
        $limit = ProductCatalogService::resolveLimit($params);
        $offset = ProductCatalogService::resolveOffset($params, $limit);
        $includeHidden = ProductCatalogService::toBool($params['include_hidden'] ?? false);
        $includeContent = ProductCatalogService::toBool($params['include_content'] ?? false);
        $parent = (int) ($params['parent'] ?? 0);

        if ($parent > 0 && $this->findVisibleCategory($parent, $params, $includeHidden) === null) {
            return [
                'items' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
            ];
        }

        $total = $this->countList($params, $parent, $includeHidden);

        $listQuery = $this->buildListQuery($params, $parent, $includeHidden);
        [$sortField, $dir] = self::resolveSort($params);
        $listQuery->sortby($sortField, $dir);
        $listQuery->limit($limit, $offset);

        return [
            'items' => $this->fetchFormattedCategories($listQuery, $includeContent),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{items: list<array<string, mixed>>}
     */
    public function getTree(array $params): array
    {
        $parent = (int) ($params['parent'] ?? 0);
        $depth = self::resolveDepth($params);
        $includeHidden = ProductCatalogService::toBool($params['include_hidden'] ?? false);

        if ($parent > 0 && $this->findVisibleCategory($parent, $params, $includeHidden) === null) {
            return ['items' => []];
        }

        $rows = $this->loadVisibleCategoryRows($params, $includeHidden);
        [$byId, $childrenByParent] = $this->indexCategoryRows($rows);

        return [
            'items' => self::buildTreeNodes($byId, $childrenByParent, $parent, $depth),
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function publicCriteria(array $extra = []): array
    {
        return array_merge([
            'class_key' => msCategory::class,
            'published' => 1,
            'deleted' => 0,
        ], $extra);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveContext(array $params): string
    {
        return trim((string) ($params['context'] ?? $this->modx->context->key ?? 'web'));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function findVisibleCategory(int $categoryId, array $params, bool $includeHidden): ?msCategory
    {
        $criteria = $this->publicCriteria(array_merge(
            ['id' => $categoryId],
            $this->visibilityCriteria($params, $includeHidden),
        ));

        /** @var msCategory|null $category */
        $category = $this->modx->getObject(msCategory::class, $criteria);

        return $category ?: null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function countList(array $params, int $parent, bool $includeHidden): int
    {
        $countQuery = $this->buildListQuery($params, $parent, $includeHidden);
        $countQuery->select('COUNT(msCategory.id)');
        if (!$countQuery->prepare() || !$countQuery->stmt->execute()) {
            return 0;
        }

        return (int) $countQuery->stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildListQuery(array $params, int $parent, bool $includeHidden): xPDOQuery
    {
        return $this->createVisibleCategoriesQuery($params, $includeHidden, ['parent' => $parent]);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function loadVisibleCategoryRows(array $params, bool $includeHidden): array
    {
        $c = $this->createVisibleCategoriesQuery($params, $includeHidden);

        [$sortField, $dir] = self::resolveSort($params);
        $c->sortby($sortField, $dir);
        $c->select($this->modx->getSelectColumns(msCategory::class, 'msCategory', '', self::RESOURCE_FIELDS));

        if (!$c->prepare() || !$c->stmt->execute()) {
            return [];
        }

        $rows = $c->stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, list<int>>}
     */
    private function indexCategoryRows(array $rows): array
    {
        $byId = [];
        $childrenByParent = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $payload = $this->normalizeRowPayload($row, false);
            $byId[$id] = $payload;
            $parentId = (int) ($payload['parent'] ?? 0);
            $childrenByParent[$parentId][] = $id;
        }

        return [$byId, $childrenByParent];
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function listDirectChildrenPayloads(
        int $parentId,
        array $params,
        bool $includeHidden,
        bool $includeContent,
    ): array {
        $listQuery = $this->buildListQuery($params, $parentId, $includeHidden);
        [$sortField, $dir] = self::resolveSort($params);
        $listQuery->sortby($sortField, $dir);

        return $this->fetchFormattedCategories($listQuery, $includeContent);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function buildBreadcrumbs(msCategory $category, array $params, bool $includeHidden): array
    {
        $context = $this->resolveContext($params);
        $ids = $this->modx->getParentIds((int) $category->get('id'), 10, [
            'context' => $context !== '' ? $context : (string) $category->get('context_key'),
        ]);

        if (!is_array($ids)) {
            $ids = [];
        }

        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        $ids = array_reverse($ids);
        $ids[] = (int) $category->get('id');

        if ($ids === []) {
            return [$this->formatBreadcrumbPayload($this->formatCategory($category, false))];
        }

        $criteria = $this->publicCriteria(array_merge(
            ['id:IN' => $ids],
            $this->visibilityCriteria($params, $includeHidden),
        ));

        $query = $this->modx->newQuery(msCategory::class, $criteria);
        $query->select($this->modx->getSelectColumns(msCategory::class, 'msCategory', '', self::RESOURCE_FIELDS));

        /** @var array<int, array<string, mixed>> $byId */
        $byId = [];
        if ($query->prepare() && $query->stmt->execute()) {
            while ($row = $query->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $byId[$id] = $this->formatBreadcrumbPayload($this->normalizeRowPayload($row, false));
                }
            }
        }

        $crumbs = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $crumbs[] = $byId[$id];
            }
        }

        return $crumbs;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function createVisibleCategoriesQuery(
        array $params,
        bool $includeHidden,
        array $extra = [],
    ): xPDOQuery {
        $c = $this->modx->newQuery(msCategory::class);
        $c->where($this->publicCriteria($extra));
        $this->applyVisibilityFilters($c, $params, $includeHidden);

        return $c;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFormattedCategories(xPDOQuery $query, bool $includeContent): array
    {
        /** @var list<msCategory> $categories */
        $categories = $this->modx->getCollection(msCategory::class, $query) ?: [];

        $items = [];
        foreach ($categories as $category) {
            $items[] = $this->formatCategory($category, $includeContent);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function visibilityCriteria(array $params, bool $includeHidden): array
    {
        $criteria = $this->contextCriteria($params);
        if (!$includeHidden) {
            $criteria['hidemenu'] = 0;
        }

        return $criteria;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function contextCriteria(array $params): array
    {
        $context = $this->resolveContext($params);

        return $context !== '' ? ['context_key' => $context] : [];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function applyVisibilityFilters(xPDOQuery $query, array $params, bool $includeHidden): void
    {
        foreach ($this->visibilityCriteria($params, $includeHidden) as $field => $value) {
            $query->where(["msCategory.{$field}" => $value]);
        }
    }

    private function castResourceField(string $field, mixed $value): mixed
    {
        if ($field === 'hidemenu') {
            return (bool) $value;
        }
        if (in_array($field, self::INT_FIELDS, true)) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCategory(msCategory $category, bool $includeContent): array
    {
        $payload = [];
        foreach (self::RESOURCE_FIELDS as $field) {
            $payload[$field] = $this->castResourceField($field, $category->get($field));
        }

        if ($includeContent) {
            $payload['content'] = $category->get('content');
        }

        return self::whitelistPublicPayload($payload, $includeContent);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRowPayload(array $row, bool $includeContent): array
    {
        $payload = [];
        foreach (self::RESOURCE_FIELDS as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            $payload[$field] = $this->castResourceField($field, $row[$field]);
        }

        if ($includeContent && array_key_exists('content', $row)) {
            $payload['content'] = $row['content'];
        }

        return self::whitelistPublicPayload($payload, $includeContent);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function formatBreadcrumbPayload(array $payload): array
    {
        $result = [];
        foreach (self::BREADCRUMB_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $result[$field] = $payload[$field];
            }
        }

        return $result;
    }
}
