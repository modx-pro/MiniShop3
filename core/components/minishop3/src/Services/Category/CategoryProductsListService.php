<?php

declare(strict_types=1);

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductOption;
use MiniShop3\Services\Grid\GridOptionColumnResolver;
use MiniShop3\Services\Grid\GridRelationColumnResolver;
use MiniShop3\Services\Grid\OptionColumnSpec;
use MiniShop3\Services\Grid\ProductDataColumnPolicy;
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
        $dataColumns = ProductDataColumnPolicy::extraReadableColumns(
            $gridFields,
            $this->modx->getFieldMeta(msProductData::class)
        );

        $scopeCategoryIds = $nested
            ? $this->treeService()->productParentIds($categoryId, true)
            : [$categoryId];

        $c = $this->buildProductListQuery($scopeCategoryIds, $params, $optionSpecs, $relationSpecs);

        $countQuery = $this->buildProductListQuery($scopeCategoryIds, $params, $optionSpecs, $relationSpecs);
        $countQuery->select('COUNT(DISTINCT msProduct.id)');
        $total = $this->executeGridQuery($countQuery, 'count')
            ? (int) $countQuery->stmt->fetchColumn()
            : 0;

        $effectiveMenuindexSql = CategoryProductMenuindexService::effectiveMenuindexSqlForCategories($scopeCategoryIds);
        $sortField = $this->mapSortField($sortBy, $optionSpecs, $relationSpecs, $dataColumns, $effectiveMenuindexSql);
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
        foreach (array_keys($dataColumns) as $dataColumnName) {
            $selectParts[] = "Data.`{$dataColumnName}`";
        }
        $selectParts[] = "{$effectiveMenuindexSql} AS effective_menuindex";
        // Pass an array: select() explodes a string on commas and backtick-quotes bare pieces,
        // so "parent IN (3,5,7)" in the effective menuindex expression became IN (3,`5`,7).
        // @phpstan-ignore argument.type (xPDO docblock says string; arrays are supported and kept intact)
        $c->select($selectParts);
        if ($optionSpecs !== [] || count($scopeCategoryIds) > 1) {
            $c->groupby('msProduct.id');
        }

        $rows = [];
        if ($this->executeGridQuery($c, 'list')) {
            $rows = $c->stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            // Keep the total consistent with the rows: a count from the successful count query
            // over an empty grid reads as a paginator pointing at pages that never load.
            $total = 0;
        }

        $passthroughFieldNames = array_merge(
            array_map(static fn (OptionColumnSpec $s) => $s->fieldName, $optionSpecs),
            array_map(static fn (RelationColumnSpec $s) => $s->fieldName, $relationSpecs),
        );
        $results = [];
        foreach ($rows as $row) {
            $results[] = $this->formatProductRow($row, $nested, $passthroughFieldNames, $dataColumns);
        }

        return [
            'results' => $results,
            'total' => $total,
        ];
    }

    /**
     * Prepare and execute a grid query, logging a failure with the SQL that caused it.
     *
     * @param string $context Which of the two grid queries failed: 'count' or 'list'
     */
    private function executeGridQuery(xPDOQuery $query, string $context): bool
    {
        $stmt = $query->prepare();
        if ($stmt instanceof \PDOStatement && $stmt->execute()) {
            return true;
        }

        $error = $stmt instanceof \PDOStatement
            ? (string) json_encode($stmt->errorInfo(), JSON_UNESCAPED_UNICODE)
            : 'statement not prepared';

        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            "[MiniShop3] Category products grid: {$context} query failed: {$error}"
            . ' SQL: ' . $query->toSQL()
        );

        return false;
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
     * @param array<string, string>     $dataColumns Extra msProductData columns (name => phptype)
     */
    private function mapSortField(
        string $sortBy,
        array $optionSpecs,
        array $relationSpecs,
        array $dataColumns,
        string $effectiveMenuindexSql,
    ): string {
        $optionSql = [];
        foreach ($optionSpecs as $spec) {
            $optionSql[$spec->fieldName] = $this->aggregateOptionValueSql($spec->alias);
        }
        $relationSql = [];
        foreach ($relationSpecs as $spec) {
            $relationSql[$spec->fieldName] = $spec->sortExpression();
        }

        return CategoryProductSort::expression(
            $sortBy,
            $optionSql,
            $relationSql,
            $dataColumns,
            $effectiveMenuindexSql,
        );
    }

    /**
     * @param list<int>               $scopeCategoryIds
     * @param list<OptionColumnSpec>  $optionSpecs
     * @param list<RelationColumnSpec> $relationSpecs
     */
    private function buildProductListQuery(
        array $scopeCategoryIds,
        array $params,
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

        $memberAlias = CategoryProductMenuindexService::MEMBER_JOIN_ALIAS;
        $c->leftJoin(
            msCategoryMember::class,
            $memberAlias,
            CategoryProductMenuindexService::memberJoinOnCategories($scopeCategoryIds, $memberAlias)
        );

        $c->where(['msProduct.class_key' => msProduct::class]);

        $scopeService = $this->getCategoryProductScopeService();
        $scopeService->applyProductCategoryScope($c, $scopeCategoryIds);

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

        // Filter by msProductData extra columns (custom fields from xPDO map)
        /** @var array<string, string> $dataColumns */
        foreach ($dataColumns as $fieldName => $phptype) {
            // Support both filter_<col> and direct <col> param names
            $paramKey = 'filter_' . $fieldName;
            $directKey = $fieldName;
            $filterValue = $params[$paramKey] ?? $params[$directKey] ?? null;

            if ($filterValue === null || $filterValue === '') {
                continue;
            }

            // Skip non-scalar values (e.g., filter_x[]=1) to prevent "Array to string conversion"
            if (!is_scalar($filterValue)) {
                continue;
            }

            $filterValue = (string) $filterValue;

            // Apply filter based on phptype
            match (strtolower($phptype)) {
                'string', 'text' => $c->where(["Data.`{$fieldName}`:LIKE" => "%{$filterValue}%"]),
                'integer', 'float' => $c->where(["Data.`{$fieldName}`" => (float) $filterValue]),
                'boolean' => $c->where(["Data.`{$fieldName}`" => (int) (bool) $filterValue]),
                'date', 'datetime' => $c->where(["Data.`{$fieldName}`:LIKE" => "{$filterValue}%"]),
                // json, internal fields excluded from filtering
                default => null,
            };
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
     * @param list<string>        $passthroughFieldNames Option and relation column names
     * @param array<string,string> $dataColumns          Extra msProductData columns (name => phptype)
     *
     * @return array<string, mixed>
     */
    private function formatProductRow(
        array $row,
        bool $nested,
        array $passthroughFieldNames,
        array $dataColumns,
    ): array {
        $id = (int) $row['id'];
        $data = [
            'id' => $id,
            'pagetitle' => $row['pagetitle'] ?? '',
            'longtitle' => $row['longtitle'] ?? '',
            'alias' => $row['alias'] ?? '',
            'parent' => (int) ($row['parent'] ?? 0),
            'menuindex' => (int) ($row['effective_menuindex'] ?? $row['menuindex'] ?? 0),
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

        $allowedPassthrough = array_flip($passthroughFieldNames);
        foreach ($row as $key => $value) {
            if (!array_key_exists($key, $data) && isset($allowedPassthrough[$key])) {
                $data[$key] = $value;
            }
        }

        foreach ($dataColumns as $name => $phptype) {
            if (array_key_exists($name, $row)) {
                $data[$name] = ProductDataColumnPolicy::castValue($row[$name], $phptype);
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
