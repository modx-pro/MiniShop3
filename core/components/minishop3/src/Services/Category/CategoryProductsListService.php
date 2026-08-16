<?php

declare(strict_types=1);

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductOption;
use MiniShop3\Services\Grid\GridOptionColumnResolver;
use MiniShop3\Services\Grid\GridRelationColumnResolver;
use MiniShop3\Services\Grid\OptionColumnSpec;
use MiniShop3\Services\Grid\RelationColumnSpec;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Category products grid: SQL list query with optional option columns (JOIN + GROUP BY + GROUP_CONCAT)
 * and relation columns (JOIN + SELECT from related tables).
 *
 * Multi-value options appear as one string (MySQL GROUP_CONCAT). For very large sets, server
 * `group_concat_max_len` may truncate the result.
 */
final class CategoryProductsListService
{
    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $gridFields Full grid config rows (visible or all)
     * @param array<string, mixed>             $params     Request query params (filters, query, …)
     *
     * @return array{results: list<array<string, mixed>>, total: int}
     */
    public function getPage(
        int $categoryId,
        array $params,
        bool $nested,
        array $gridFields,
        int $start,
        int $limit,
        string $sortBy,
        string $sortDir,
    ): array {
        $optionSpecs = GridOptionColumnResolver::resolve($gridFields);
        $relationSpecs = GridRelationColumnResolver::resolve($this->modx, $gridFields);

        $c = $this->buildProductListQuery($categoryId, $params, $nested, $optionSpecs, $relationSpecs);

        $countQuery = $this->buildProductListQuery($categoryId, $params, $nested, $optionSpecs, $relationSpecs);
        $countQuery->select('COUNT(DISTINCT msProduct.id)');
        $countQuery->prepare();
        $countQuery->stmt->execute();
        $total = (int) $countQuery->stmt->fetchColumn();

        $sortField = $this->mapSortField($sortBy, $optionSpecs, $relationSpecs);
        $c->sortby($sortField, $sortDir);
        $c->limit($limit, $start);

        $selectParts = [
            'msProduct.*',
            'Data.article',
            'Data.price',
            'Data.old_price',
            'Data.weight',
            'Data.image',
            'Data.thumb',
            'Data.vendor_id',
            'Data.made_in',
            'Data.new',
            'Data.popular',
            'Data.favorite',
        ];
        foreach ($optionSpecs as $spec) {
            $selectParts[] = $this->aggregateOptionValueSql($spec->alias) . " AS `{$spec->fieldName}`";
        }
        foreach ($relationSpecs as $spec) {
            $selectParts[] = $spec->selectExpression();
        }
        // xPDOQuery::select() declares string, accepts both at runtime but PHPStan is strict.
        $c->select(implode(', ', $selectParts));
        if ($optionSpecs !== []) {
            $c->groupby('msProduct.id');
        }

        $c->prepare();
        $rows = $c->stmt->execute() ? $c->stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        $optionFieldNames = array_map(static fn (OptionColumnSpec $s) => $s->fieldName, $optionSpecs);
        $relationFieldNames = array_map(static fn (RelationColumnSpec $s) => $s->fieldName, $relationSpecs);
        $results = [];
        foreach ($rows as $row) {
            $results[] = $this->formatProductRow($row, $nested, $optionFieldNames, $relationFieldNames);
        }

        return [
            'results' => $results,
            'total' => $total,
        ];
    }

    /**
     * Single aggregate expression for an option alias (SELECT list + ORDER BY under ONLY_FULL_GROUP_BY).
     */
    private function aggregateOptionValueSql(string $alias): string
    {
        return "GROUP_CONCAT(DISTINCT `{$alias}`.value)";
    }

    /**
     * @param list<OptionColumnSpec>    $optionSpecs
     * @param list<RelationColumnSpec>  $relationSpecs
     */
    private function mapSortField(string $sortBy, array $optionSpecs, array $relationSpecs): string
    {
        foreach ($optionSpecs as $spec) {
            if ($spec->fieldName === $sortBy) {
                return $this->aggregateOptionValueSql($spec->alias);
            }
        }
        foreach ($relationSpecs as $spec) {
            if ($spec->fieldName === $sortBy) {
                return $spec->sortExpression();
            }
        }
        $productFields = ['id', 'pagetitle', 'menuindex', 'published', 'createdon', 'editedon'];
        if (in_array($sortBy, $productFields, true)) {
            return "msProduct.{$sortBy}";
        }
        $dataFields = ['article', 'price', 'old_price', 'weight', 'vendor_id', 'made_in'];
        if (in_array($sortBy, $dataFields, true)) {
            return "Data.{$sortBy}";
        }

        return "msProduct.{$sortBy}";
    }

    /**
     * @param list<OptionColumnSpec>    $optionSpecs
     * @param list<RelationColumnSpec>  $relationSpecs
     */
    private function buildProductListQuery(
        int $categoryId,
        array $params,
        bool $nested,
        array $optionSpecs,
        array $relationSpecs,
    ): xPDOQuery {
        $query = trim((string) ($params['query'] ?? ''));
        $c = $this->modx->newQuery(msProduct::class);
        $c->innerJoin(msProductData::class, 'Data', 'msProduct.id = Data.id');

        foreach ($optionSpecs as $spec) {
            $alias = $spec->alias;
            $key = $this->quoteOptionKeyForJoinCondition($spec->key);
            $c->leftJoin(
                msProductOption::class,
                $alias,
                "`{$alias}`.product_id = msProduct.id AND `{$alias}`.key = '{$key}'"
            );
        }

        foreach (GridRelationColumnResolver::uniqueJoins($relationSpecs) as $spec) {
            $c->leftJoin($spec->modelClass, $spec->alias, $spec->joinCondition());
        }

        $c->where(['msProduct.class_key' => msProduct::class]);

        $scopeService = $this->getCategoryProductScopeService();
        if ($nested) {
            $categoryIds = $this->treeService()->productParentIds($categoryId, true);
            $scopeService->applyProductCategoryScope($c, $categoryIds);
        } else {
            $scopeService->applyProductCategoryScope($c, [$categoryId]);
        }

        if ($query !== '') {
            $c->where([
                'msProduct.pagetitle:LIKE' => "%{$query}%",
                'OR:Data.article:LIKE' => "%{$query}%",
            ]);
        }

        $productBooleanFields = ['published', 'deleted', 'hidemenu', 'isfolder'];
        foreach ($productBooleanFields as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                $c->where(["msProduct.{$field}" => (int) $params[$field]]);
            }
        }

        $dataBooleanFields = ['new', 'popular', 'favorite'];
        foreach ($dataBooleanFields as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                $c->where(["Data.{$field}" => (int) $params[$field]]);
            }
        }

        $productTextFields = ['pagetitle', 'longtitle', 'alias', 'description', 'introtext', 'content'];
        foreach ($productTextFields as $field) {
            if (!empty($params[$field])) {
                $c->where(["msProduct.{$field}:LIKE" => "%{$params[$field]}%"]);
            }
        }

        $dataTextFields = ['article', 'made_in'];
        foreach ($dataTextFields as $field) {
            if (!empty($params[$field])) {
                $c->where(["Data.{$field}:LIKE" => "%{$params[$field]}%"]);
            }
        }

        $dataNumericFields = ['price', 'old_price', 'weight', 'vendor_id'];
        foreach ($dataNumericFields as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                $c->where(["Data.{$field}" => $params[$field]]);
            }
        }

        foreach ($optionSpecs as $spec) {
            $paramKey = 'filter_' . $spec->fieldName;
            if (isset($params[$paramKey]) && $params[$paramKey] !== '') {
                $c->where(["`{$spec->alias}`.value:LIKE" => "%{$params[$paramKey]}%"]);
            }
        }

        foreach ($relationSpecs as $spec) {
            $paramKey = 'filter_' . $spec->fieldName;
            if (isset($params[$paramKey]) && $params[$paramKey] !== '') {
                $c->where([$spec->sortExpression() . ':LIKE' => "%{$params[$paramKey]}%"]);
            }
        }

        if (!isset($params['deleted']) || $params['deleted'] === '') {
            $c->where(['msProduct.deleted' => 0]);
        }

        return $c;
    }

    private function treeService(): CategoryTreeService
    {
        $service = $this->modx->services->get('ms3_category_tree');

        return $service instanceof CategoryTreeService
            ? $service
            : new CategoryTreeService($this->modx);
    }

    /**
     * Option keys are validated to [a-z0-9_]; still escape single quotes for SQL string literals.
     */
    private function quoteOptionKeyForJoinCondition(string $key): string
    {
        return str_replace("'", "''", $key);
    }

    /**
     * Parent category IDs allowed for products in category grid scope (matches list filter).
     *
     * @return list<int>
     */
    public function getAllowedProductParentCategoryIds(int $categoryId, bool $nested): array
    {
        return CategoryProductScopePolicy::allowedParentCategoryIds(
            $categoryId,
            $nested,
            $nested ? $this->treeService()->getDescendantCategoryIds($categoryId) : []
        );
    }

    /**
     * Whether a product belongs to the category products grid scope (direct parent or nested tree).
     */
    public function isProductInCategoryScope(int $productId, int $categoryId, bool $nested): bool
    {
        $product = $this->modx->getObject(msProduct::class, $productId);
        if (!$product) {
            return false;
        }

        return CategoryProductScopePolicy::isParentInScope(
            (int) $product->get('parent'),
            $categoryId,
            $nested,
            $nested ? $this->treeService()->getDescendantCategoryIds($categoryId) : []
        );
    }

    /**
     * @param list<string> $optionFieldNames   Allowed option field names (whitelist)
     * @param list<string> $relationFieldNames Allowed relation field names (whitelist)
     *
     * @return array<string, mixed>
     */
    private function formatProductRow(
        array $row,
        bool $nested,
        array $optionFieldNames,
        array $relationFieldNames,
    ): array {
        $id = (int) $row['id'];
        $data = [
            'id' => $id,
            'pagetitle' => $row['pagetitle'] ?? '',
            'longtitle' => $row['longtitle'] ?? '',
            'alias' => $row['alias'] ?? '',
            'parent' => (int) ($row['parent'] ?? 0),
            'menuindex' => (int) ($row['menuindex'] ?? 0),
            'published' => (bool) ($row['published'] ?? false),
            'deleted' => (bool) ($row['deleted'] ?? false),
            'hidemenu' => (bool) ($row['hidemenu'] ?? false),
            'createdon' => $row['createdon'] ?? null,
            'editedon' => $row['editedon'] ?? null,
            'article' => $row['article'] ?? '',
            'price' => (float) ($row['price'] ?? 0),
            'old_price' => (float) ($row['old_price'] ?? 0),
            'weight' => (float) ($row['weight'] ?? 0),
            'image' => $row['image'] ?? '',
            'thumb' => $row['thumb'] ?? '',
            'vendor_id' => (int) ($row['vendor_id'] ?? 0),
            'made_in' => $row['made_in'] ?? '',
            'new' => (bool) ($row['new'] ?? false),
            'popular' => (bool) ($row['popular'] ?? false),
            'favorite' => (bool) ($row['favorite'] ?? false),
            'preview_url' => $this->modx->makeUrl($id, '', '', 'full'),
        ];

        $allowedExtraFields = array_flip(array_merge($optionFieldNames, $relationFieldNames));
        foreach ($row as $key => $value) {
            if (!array_key_exists($key, $data) && isset($allowedExtraFields[$key])) {
                $data[$key] = $value;
            }
        }

        if ($nested && (int) ($row['parent'] ?? 0) !== 0) {
            $parent = $this->modx->getObject(msCategory::class, (int) $row['parent']);
            if ($parent) {
                $data['category_name'] = $parent->get('pagetitle');
            }
        }

        return $data;
    }

    private function getCategoryProductScopeService(): CategoryProductScopeService
    {
        if ($this->modx->services->has('ms3_category_product_scope')) {
            $service = $this->modx->services->get('ms3_category_product_scope');
            if ($service instanceof CategoryProductScopeService) {
                return $service;
            }
        }

        return new CategoryProductScopeService($this->modx);
    }
}
