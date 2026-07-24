<?php

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;

/**
 * Resolves msProduct membership in a category (parent = category id).
 *
 * Used by CategoryProductsController mutations to prevent IDOR across categories.
 */
class CategoryProductScopeService
{
    public function __construct(protected modX $modx)
    {
    }

    /**
     * Load product only when it belongs to the given category.
     */
    public function findInCategory(int $categoryId, int $productId): ?msProduct
    {
        if ($categoryId <= 0 || $productId <= 0) {
            return null;
        }

        /** @var msProduct|null $product */
        $product = $this->modx->getObject(msProduct::class, [
            'id' => $productId,
            'parent' => $categoryId,
        ]);

        return $product;
    }
}
