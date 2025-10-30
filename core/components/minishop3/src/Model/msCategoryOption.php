<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOObject;

/**
 * Class msCategoryOption
 *
 * @property integer $option_id
 * @property integer $category_id
 * @property integer $position
 * @property boolean $active
 * @property boolean $required
 * @property string $value
 *
 * @package MiniShop3\Model
 */
class msCategoryOption extends xPDOObject
{
    /**
     * Auto-assign option to products in category after save
     *
     * Lifecycle hook that triggers auto-assignment of option to all products
     * when option is added to category or updated.
     *
     * Delegates to OptionCategoryService for optimized batch operations
     *
     * @param null $cacheFlag
     * @return bool
     */
    public function save($cacheFlag = null)
    {
        // Save the link first
        $save = parent::save($cacheFlag);

        if (!$save) {
            return false;
        }

        // Delegate auto-assignment to service (optimized with batch operations)
        $service = $this->xpdo->services->get('ms3_option_service');
        $categoryService = $service->getCategory();

        $productIds = $categoryService->getProductsInCategory($this->get('category_id'));

        if (empty($productIds)) {
            return $save;
        }

        $option = $this->xpdo->getObject(msOption::class, $this->get('option_id'));
        if (!$option) {
            return $save;
        }

        $key = $option->get('key');

        // Find products that already have this option
        $existingProductIds = $categoryService->getProductsWithOption($productIds, $key);

        // Add option only to products that don't have it yet (batch INSERT)
        $newProductIds = array_diff($productIds, $existingProductIds);

        if (!empty($newProductIds)) {
            $categoryService->batchInsertOptions($newProductIds, $key, $this->get('value'));
        }

        return $save;
    }

    /**
     * Remove option from category with smart data preservation
     *
     * Lifecycle hook that removes option values from products,
     * but checks if option is still active in other categories first.
     *
     * IMPROVEMENT: Prevents data loss when product is in multiple categories
     * and option is active in more than one.
     *
     * Delegates to OptionService::removeOptionFromCategory()
     *
     * @param array $ancestors
     * @return bool
     */
    public function remove(array $ancestors = [])
    {
        // Delegate to service with smart data preservation
        $service = $this->xpdo->services->get('ms3_option_service');
        $categoryService = $service->getCategory();

        $productIds = $categoryService->getProductsInCategory($this->get('category_id'));

        if (!empty($productIds)) {
            $option = $this->xpdo->getObject(msOption::class, $this->get('option_id'));
            if ($option) {
                $key = $option->get('key');

                // ✅ IMPROVEMENT: Check each product if option is active in other categories
                foreach ($productIds as $productId) {
                    // Check if option is active in other categories for this product
                    $otherCategories = $categoryService->getOtherCategoriesWithOption(
                        $productId,
                        $this->get('option_id'),
                        $this->get('category_id')
                    );

                    // Delete option value only if NOT active in other categories
                    if (empty($otherCategories)) {
                        $this->xpdo->removeCollection(msProductOption::class, [
                            'product_id' => $productId,
                            'key' => $key
                        ]);
                    }
                }
            }
        }

        return parent::remove($ancestors);
    }

}
