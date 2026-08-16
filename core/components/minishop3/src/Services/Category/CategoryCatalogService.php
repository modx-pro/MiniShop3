<?php

declare(strict_types=1);

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Services\Catalog\CatalogQuery;
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

    /** Hard cap for nodes loaded into a tree response (depth window still applied). */
    private const MAX_TREE_NODES = 500;

    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: string}
     */
    public static function resolveSort(array $params): array
    {
        return CatalogQuery::resolveSort($params, self::SORT_MAP);
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

        $includeHidden = CatalogQuery::toBool($params['include_hidden'] ?? false);
        $category = $this->findVisibleCategory($categoryId, $params, $includeHidden);
        if ($category === null) {
            return null;
        }

        $includeContent = CatalogQuery::toBool($params['include_content'] ?? false);
        $payload = $this->formatCategory($category, $includeContent);

        $includeBreadcrumbs = !isset($params['include_breadcrumbs'])
            || CatalogQuery::toBool($params['include_breadcrumbs']);
        if ($includeBreadcrumbs) {
            $payload['breadcrumbs'] = $this->buildBreadcrumbs($category, $params, $includeHidden);
        }

        if (CatalogQuery::toBool($params['include_children'] ?? false)) {
            $payload['children'] = $this->listDirectChildrenPayloads(
                $categoryId,
                $params,
                $includeHidden,
                CatalogQuery::resolveLimit($params),
            );
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{items: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function getList(array $params): array
    {
        $limit = CatalogQuery::resolveLimit($params);
        $offset = CatalogQuery::resolveOffset($params, $limit);
        $includeHidden = CatalogQuery::toBool($params['include_hidden'] ?? false);
        $includeContent = CatalogQuery::toBool($params['include_content'] ?? false);
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
        $this->applySort($listQuery, $params);
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
        $depth = CatalogQuery::resolveDepth($params);
        $includeHidden = CatalogQuery::toBool($params['include_hidden'] ?? false);

        if ($parent > 0 && $this->findVisibleCategory($parent, $params, $includeHidden) === null) {
            return ['items' => []];
        }

        $rows = $this->loadTreeWindowRows($params, $includeHidden, $parent, $depth);
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
        return CatalogQuery::resolveContext(
            $params,
            (string) ($this->modx->context->key ?? 'web'),
        );
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
     * Load only categories inside the parent + depth window (BFS), capped at MAX_TREE_NODES.
     *
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function loadTreeWindowRows(
        array $params,
        bool $includeHidden,
        int $rootParent,
        int $depth,
    ): array {
        if ($depth < 1) {
            return [];
        }

        $rows = [];
        $parentIds = [$rootParent];
        $remaining = self::MAX_TREE_NODES;

        for ($level = 0; $level < $depth && $parentIds !== [] && $remaining > 0; $level++) {
            $levelRows = $this->loadChildrenRowsForParents($params, $includeHidden, $parentIds, $remaining);
            if ($levelRows === []) {
                break;
            }

            $nextParents = [];
            foreach ($levelRows as $row) {
                $rows[] = $row;
                $remaining--;
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $nextParents[] = $id;
                }
            }
            $parentIds = $nextParents;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $params
     * @param list<int> $parentIds
     * @return list<array<string, mixed>>
     */
    private function loadChildrenRowsForParents(
        array $params,
        bool $includeHidden,
        array $parentIds,
        int $limit,
    ): array {
        if ($parentIds === [] || $limit < 1) {
            return [];
        }

        $c = $this->createVisibleCategoriesQuery($params, $includeHidden, [
            'parent:IN' => $parentIds,
        ]);
        $this->applySort($c, $params);
        $c->select($this->modx->getSelectColumns(msCategory::class, 'msCategory', '', self::RESOURCE_FIELDS));
        $c->limit($limit);

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
            $payload = $this->normalizeRowPayload($row);
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
        int $limit,
    ): array {
        $listQuery = $this->buildListQuery($params, $parentId, $includeHidden);
        $this->applySort($listQuery, $params);
        $listQuery->limit(max(1, $limit));

        return $this->fetchFormattedCategories($listQuery, false);
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
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function buildBreadcrumbs(msCategory $category, array $params, bool $includeHidden): array
    {
        $context = $this->resolveContext($params);
        $ids = $this->modx->getParentIds((int) $category->get('id'), 10, [
            'context' => $context,
        ]);

        if (!is_array($ids)) {
            $ids = [];
        }

        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        $ids = array_reverse($ids);
        $ids[] = (int) $category->get('id');

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
                    $byId[$id] = $this->formatBreadcrumbPayload($this->normalizeRowPayload($row));
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
    private function normalizeRowPayload(array $row): array
    {
        $payload = [];
        foreach (self::RESOURCE_FIELDS as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            $payload[$field] = $this->castResourceField($field, $row[$field]);
        }

        return self::whitelistPublicPayload($payload, false);
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
