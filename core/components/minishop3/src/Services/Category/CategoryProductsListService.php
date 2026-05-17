<?php

declare(strict_types=1);

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductOption;
use MiniShop3\Services\Grid\GridOptionColumnResolver;
use MiniShop3\Services\Grid\OptionColumnSpec;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Category products grid: SQL list query with optional option columns (JOIN + GROUP BY + GROUP_CONCAT).
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

        $c = $this->buildProductListQuery($categoryId, $params, $nested, $optionSpecs);

        $countQuery = $this->buildProductListQuery($categoryId, $params, $nested, $optionSpecs);
        $countQuery->select('COUNT(DISTINCT msProduct.id)');
        $countQuery->prepare();
        $countQuery->stmt->execute();
        $total = (int) $countQuery->stmt->fetchColumn();

        $sortField = $this->mapSortField($sortBy, $optionSpecs);
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
        $c->select($selectParts);
        if ($optionSpecs !== []) {
            $c->groupby('msProduct.id');
        }

        $c->prepare();
        $rows = $c->stmt->execute() ? $c->stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        $optionFieldNames = array_map(static fn (OptionColumnSpec $s) => $s->fieldName, $optionSpecs);
        $results = [];
        foreach ($rows as $row) {
            $results[] = $this->formatProductRow($row, $nested, $optionFieldNames);
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
     * @param list<OptionColumnSpec> $optionSpecs
     */
    private function mapSortField(string $sortBy, array $optionSpecs): string
    {
        foreach ($optionSpecs as $spec) {
            if ($spec->fieldName === $sortBy) {
                return $this->aggregateOptionValueSql($spec->alias);
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
     * @param list<OptionColumnSpec> $optionSpecs
     */
    private function buildProductListQuery(int $categoryId, array $params, bool $nested, array $optionSpecs): xPDOQuery
    {
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

        $c->where(['msProduct.class_key' => msProduct::class]);

        if ($nested) {
            $categoryIds = $this->getChildCategories($categoryId);
            $categoryIds[] = $categoryId;
            $c->where(['msProduct.parent:IN' => $categoryIds]);
        } else {
            $c->where(['msProduct.parent' => $categoryId]);
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

        if (!isset($params['deleted']) || $params['deleted'] === '') {
            $c->where(['msProduct.deleted' => 0]);
        }

        return $c;
    }

    /**
     * Option keys are validated to [a-z0-9_]; still escape single quotes for SQL string literals.
     */
    private function quoteOptionKeyForJoinCondition(string $key): string
    {
        return str_replace("'", "''", $key);
    }

    /**
     * @return list<int>
     */
    private function getChildCategories(int $parentId): array
    {
        $ids = [];

        $children = $this->modx->getIterator(msCategory::class, [
            'parent' => $parentId,
            'deleted' => 0,
            'class_key' => msCategory::class,
        ]);

        foreach ($children as $child) {
            $childId = (int) $child->get('id');
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getChildCategories($childId));
        }

        return $ids;
    }

    /**
     * @param list<string> $optionFieldNames Allowed option field names (whitelist)
     *
     * @return array<string, mixed>
     */
    private function formatProductRow(array $row, bool $nested, array $optionFieldNames): array
    {
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

        $allowedOptionFields = array_flip($optionFieldNames);
        foreach ($row as $key => $value) {
            if (!array_key_exists($key, $data) && isset($allowedOptionFields[$key])) {
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
}
