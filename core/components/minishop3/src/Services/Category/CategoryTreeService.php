<?php

declare(strict_types=1);

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MODX\Revolution\modX;

/** Descendant category ids for nested category product grids. */
final class CategoryTreeService
{
    public function __construct(private modX $modx)
    {
    }

    /**
     * Category ids allowed as msProduct.parent for list/mutations in this grid context.
     *
     * @return list<int>
     */
    public function productParentIds(int $categoryId, bool $nested): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        if (!$nested) {
            return [$categoryId];
        }

        return array_values(array_unique(array_merge(
            [$categoryId],
            $this->getDescendantCategoryIds($categoryId)
        )));
    }

    /**
     * @return list<int>
     */
    public function getDescendantCategoryIds(int $parentId): array
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
            $ids = array_merge($ids, $this->getDescendantCategoryIds($childId));
        }

        return $ids;
    }
}
