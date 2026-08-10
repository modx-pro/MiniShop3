<?php

declare(strict_types=1);

namespace MiniShop3\Services\Category;

/**
 * Pure scope rules for category products grid (same as CategoryProductsListService list filter).
 */
final class CategoryProductScopePolicy
{
    /**
     * @param list<int> $descendantCategoryIds Child category IDs (recursive, excluding root)
     */
    public static function isParentInScope(
        int $productParentId,
        int $categoryId,
        bool $nested,
        array $descendantCategoryIds
    ): bool {
        if ($productParentId <= 0 || $categoryId <= 0) {
            return false;
        }

        return in_array(
            $productParentId,
            self::allowedParentCategoryIds($categoryId, $nested, $descendantCategoryIds),
            true
        );
    }

    /**
     * @param list<int> $descendantCategoryIds Child category IDs (recursive, excluding root)
     *
     * @return list<int>
     */
    public static function allowedParentCategoryIds(
        int $categoryId,
        bool $nested,
        array $descendantCategoryIds
    ): array {
        if (!$nested) {
            return [$categoryId];
        }

        $allowed = $descendantCategoryIds;
        $allowed[] = $categoryId;

        return $allowed;
    }
}
