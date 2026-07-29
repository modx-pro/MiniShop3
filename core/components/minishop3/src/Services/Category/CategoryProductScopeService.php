<?php

declare(strict_types=1);

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Product ↔ category scope: primary parent, nested tree, and additional msCategoryMember links.
 *
 * Used by category-grid IDOR checks, msProducts WHERE, and admin category products list.
 */
class CategoryProductScopeService
{
    public function __construct(protected modX $modx)
    {
    }

    /**
     * @return msProduct|null
     */
    public function findInCategory(int $categoryId, int $productId, bool $nested = false)
    {
        if ($categoryId <= 0 || $productId <= 0) {
            return null;
        }

        $allowedParents = $this->treeService()->productParentIds($categoryId, $nested);

        if ($allowedParents === []) {
            return null;
        }

        if (!$nested) {
            /** @var msProduct|null $product */
            $product = $this->modx->getObject(msProduct::class, [
                'id' => $productId,
                'parent' => $categoryId,
            ]);

            if ($product) {
                return $product;
            }

            // Additional category membership (msCategoryMember) for the leaf category only.
            $member = $this->modx->getObject(msCategoryMember::class, [
                'category_id' => $categoryId,
                'product_id' => $productId,
            ]);

            return $member ? $this->modx->getObject(msProduct::class, $productId) : null;
        }

        /** @var msProduct|null $product */
        $product = $this->modx->getObject(msProduct::class, $productId);

        if (!$product) {
            return null;
        }

        if (in_array((int) $product->get('parent'), $allowedParents, true)) {
            return $product;
        }

        $member = $this->modx->getObject(msCategoryMember::class, [
            'category_id:IN' => $allowedParents,
            'product_id' => $productId,
        ]);

        return $member ? $product : null;
    }

    /**
     * @return array{include: list<int>, exclude: list<int>}
     */
    public static function parseParentSpec(string $parents): array
    {
        $include = [];
        $exclude = [];

        foreach (array_map('trim', explode(',', $parents)) as $part) {
            if ($part === '') {
                continue;
            }

            $id = (int) $part;
            if ($id > 0) {
                $include[] = $id;
            } elseif ($id < 0) {
                $exclude[] = abs($id);
            }
        }

        return [
            'include' => array_values(array_unique($include)),
            'exclude' => array_values(array_unique($exclude)),
        ];
    }

    /**
     * Resolve msProducts `parents` param into category IDs (include/exclude, depth expansion).
     *
     * @return list<int> Empty when nothing to filter.
     */
    public function resolveCategoryIdsFromParents(string $parents, int $depth): array
    {
        $parsed = self::parseParentSpec($parents);
        $include = $parsed['include'];
        $exclude = $parsed['exclude'];

        if ($include !== [] && $depth > 0) {
            $include = $this->expandPublishedCategoryTree($include, $depth);
        }

        if ($exclude !== [] && $depth > 0) {
            $exclude = $this->expandPublishedCategoryTree($exclude, $depth);
        }

        return self::finalizeIncludedCategoryIds($include, $exclude);
    }

    /**
     * @param list<int> $include
     * @param list<int> $exclude
     *
     * @return list<int>
     */
    public static function finalizeIncludedCategoryIds(array $include, array $exclude): array
    {
        $include = array_values(array_unique($include));
        $exclude = array_values(array_unique($exclude));

        if ($exclude === []) {
            return $include;
        }

        return array_values(array_diff($include, $exclude));
    }

    /**
     * pdoTools WHERE for resolved category IDs (parent + msCategoryMember).
     *
     * @param list<int> $categoryIds
     */
    public function buildMsProductsWhereForCategories(array $categoryIds): string
    {
        return self::buildMsProductsWhereSql(
            $categoryIds,
            $this->getAdditionalProductIds($categoryIds)
        );
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return list<int>
     */
    public function getAdditionalProductIds(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if ($categoryIds === []) {
            return [];
        }

        $query = $this->modx->newQuery(msCategoryMember::class);
        $query->where(['category_id:IN' => $categoryIds]);
        $query->select('product_id');

        if (!$query->prepare() || !$query->stmt->execute()) {
            return [];
        }

        $ids = $query->stmt->fetchAll(\PDO::FETCH_COLUMN);

        return array_values(array_unique(array_map('intval', $ids ?: [])));
    }

    /**
     * Raw SQL fragment for pdoTools/msProducts (`parent IN …` optionally OR member product ids).
     *
     * @param list<int> $categoryIds
     * @param list<int> $additionalProductIds
     */
    public static function buildMsProductsWhereSql(array $categoryIds, array $additionalProductIds): string
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if ($categoryIds === []) {
            throw new \InvalidArgumentException('categoryIds must not be empty');
        }

        $parentsList = implode(',', $categoryIds);

        $additionalProductIds = array_values(array_unique(array_filter(array_map('intval', $additionalProductIds))));
        if ($additionalProductIds === []) {
            return "`msProduct`.`parent` IN ({$parentsList})";
        }

        $membersList = implode(',', $additionalProductIds);

        return "(`msProduct`.`parent` IN ({$parentsList}) OR `msProduct`.`id` IN ({$membersList}))";
    }

    /**
     * xPDO where array for admin category products grid (parent + msCategoryMember).
     *
     * @param list<int> $categoryIds
     * @param list<int> $additionalProductIds
     *
     * @return array<string, mixed>
     */
    public static function buildProductCategoryScopeWhere(array $categoryIds, array $additionalProductIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if ($categoryIds === []) {
            return [];
        }

        $additionalProductIds = array_values(array_unique(array_filter(array_map('intval', $additionalProductIds))));
        if ($additionalProductIds === []) {
            return ['msProduct.parent:IN' => $categoryIds];
        }

        return [
            'msProduct.parent:IN' => $categoryIds,
            'OR:msProduct.id:IN' => $additionalProductIds,
        ];
    }

    /**
     * menuindex reorder applies only to direct children (not additional-category-only links).
     */
    public function canReorderInCategory(int $productId, int $categoryId): bool
    {
        if ($productId <= 0 || $categoryId <= 0) {
            return false;
        }

        $product = $this->modx->getObject(msProduct::class, $productId);
        if (!$product) {
            return false;
        }

        return (int) $product->get('parent') === $categoryId;
    }

    /**
     * Restrict product query to categories (primary parent or msCategoryMember).
     *
     * @param list<int> $categoryIds
     */
    public function applyProductCategoryScope(xPDOQuery $c, array $categoryIds): void
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if ($categoryIds === []) {
            return;
        }

        $where = self::buildProductCategoryScopeWhere(
            $categoryIds,
            $this->getAdditionalProductIds($categoryIds)
        );

        if ($where !== []) {
            $c->where($where);
        }
    }

    private function treeService(): CategoryTreeService
    {
        $service = $this->modx->services->get('ms3_category_tree');

        return $service instanceof CategoryTreeService
            ? $service
            : new CategoryTreeService($this->modx);
    }

    /**
     * @param list<int> $rootIds
     *
     * @return list<int>
     */
    private function expandPublishedCategoryTree(array $rootIds, int $depth): array
    {
        $allIds = $rootIds;
        $currentLevel = $rootIds;

        for ($i = 0; $i < $depth; $i++) {
            if ($currentLevel === []) {
                break;
            }

            $query = $this->modx->newQuery(msCategory::class);
            $query->where([
                'class_key' => msCategory::class,
                'parent:IN' => $currentLevel,
                'published' => 1,
                'deleted' => 0,
            ]);
            $query->select('id');

            if (!$query->prepare() || !$query->stmt->execute()) {
                break;
            }

            $childIds = array_map('intval', $query->stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);
            if ($childIds === []) {
                break;
            }

            $allIds = array_merge($allIds, $childIds);
            $currentLevel = $childIds;
        }

        return array_values(array_unique($allIds));
    }
}
