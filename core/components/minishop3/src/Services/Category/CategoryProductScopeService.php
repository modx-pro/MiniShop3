<?php

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;

/**
 * Resolves msProduct membership in a category grid context (direct or nested children).
 *
 * Used by CategoryProductsController mutations to prevent IDOR across categories.
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

            return $product;
        }

        /** @var msProduct|null $product */
        $product = $this->modx->getObject(msProduct::class, $productId);

        if (!$product) {
            return null;
        }

        return in_array((int) $product->get('parent'), $allowedParents, true) ? $product : null;
    }

    private function treeService(): CategoryTreeService
    {
        $service = $this->modx->services->get('ms3_category_tree');

        return $service instanceof CategoryTreeService
            ? $service
            : new CategoryTreeService($this->modx);
    }
}
