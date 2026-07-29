<?php

namespace MiniShop3\Services\Option;

use xPDO\xPDO;

/**
 * Main facade for option operations
 *
 * Provides unified interface for all option-related operations
 * Delegates to specialized services:
 * - OptionLoaderService: Loading options
 * - OptionSyncService: Saving/updating values
 * - OptionCategoryService: Managing category relationships
 *
 * Usage:
 * $service = $modx->services->get('ms3_option_service');
 * $options = $service->loadOptionsForProduct(123);
 * $service->saveProductOptions(123, ['color' => 'Red']);
 */
class OptionService
{
    /** @var xPDO */
    protected $xpdo;

    /** @var OptionLoaderService */
    protected $loader;

    /** @var OptionSyncService */
    protected $sync;

    /** @var OptionCategoryService */
    protected $category;

    /**
     * @param xPDO $xpdo
     * @param OptionLoaderService $loader
     * @param OptionSyncService $sync
     * @param OptionCategoryService $category
     */
    public function __construct(
        xPDO $xpdo,
        OptionLoaderService $loader,
        OptionSyncService $sync,
        OptionCategoryService $category
    ) {
        $this->xpdo = $xpdo;
        $this->loader = $loader;
        $this->sync = $sync;
        $this->category = $category;
    }

    // ========== LOADING OPERATIONS ==========

    /**
     * Load options for single product (for frontend templates)
     *
     * Replaces: msProductOption::loadOptions()
     *
     * Example:
     * $options = $service->loadOptionsForProduct(123);
     * // Returns: ['color' => ['Red'], 'size' => ['L'], 'color.caption' => 'Color', ...]
     *
     * @param int $productId Product ID
     * @param bool $includeMetadata Include option metadata (caption, description, etc.)
     * @return array Option data
     */
    public function loadOptionsForProduct(int $productId, bool $includeMetadata = true): array
    {
        return $this->loader->loadForProduct($productId, $includeMetadata);
    }

    /**
     * Load options for multiple products (batch loading for catalog pages)
     *
     * NEW METHOD - prevents N+1 problem in catalog listings
     *
     * Example:
     * $options = $service->loadOptionsForProducts([101, 102, 103]);
     * // Returns: [101 => ['color' => ['Red'], ...], 102 => [...], ...]
     *
     * @param array $productIds Array of product IDs
     * @param bool $includeMetadata Include option metadata
     * @return array Nested array: [product_id => option_data]
     */
    public function loadOptionsForProducts(array $productIds, bool $includeMetadata = false): array
    {
        return $this->loader->loadForProducts($productIds, $includeMetadata);
    }

    /**
     * Get option fields configuration for product form (admin)
     *
     * Replaces: msProductOption::getOptionFields()
     *
     * Example:
     * $fields = $service->getOptionFieldsForProduct(123);
     * // Returns array of field configs for ExtJS form
     *
     * @param int $productId Product ID
     * @param int|null $parentId Parent category ID (optional)
     * @return array Array of field configurations for ExtJS
     */
    public function getOptionFieldsForProduct(int $productId, ?int $parentId = null): array
    {
        return $this->loader->getFieldsForProduct($productId, $parentId);
    }

    /**
     * Get available option keys for product
     *
     * Replaces: msProductOption::getOptionKeys()
     *
     * Example:
     * $keys = $service->getAvailableOptionKeys(123);
     * // Returns: ['color', 'size', 'weight']
     *
     * @param int $productId Product ID
     * @param int|null $parentId Parent category ID (optional)
     * @return array Array of option keys
     */
    public function getAvailableOptionKeys(int $productId, ?int $parentId = null): array
    {
        return $this->loader->getOptionKeys($productId, $parentId);
    }

    // ========== SAVING OPERATIONS ==========

    /**
     * Save option values for product
     *
     * Replaces: msProductOption::saveProductOptions()
     * REFACTORED from 66 lines to clean service methods
     *
     * Example:
     * $service->saveProductOptions(123, [
     *     'color' => 'Red',
     *     'size' => ['S', 'M', 'L']
     * ]);
     *
     * @param int $productId Product ID
     * @param array $options Options to save ['color' => 'Red', 'size' => ['L']]
     * @param bool $removeOther Remove options not in $options array
     * @return bool Success status
     */
    public function saveProductOptions(int $productId, array $options, bool $removeOther = true): bool
    {
        return $this->sync->saveProductOptions($productId, $options, $removeOther);
    }

    /**
     * Get current option values for product
     *
     * Replaces: msProductOption::getForProduct()
     *
     * Example:
     * $values = $service->getProductOptionValues(123);
     * // Returns: ['color' => ['Red'], 'size' => ['L']]
     *
     * @param int $productId Product ID
     * @param array $keys Optional: filter by specific keys
     * @return array Option values ['key' => [values]]
     */
    public function getProductOptionValues(int $productId, array $keys = []): array
    {
        return $this->sync->getForProduct($productId, $keys);
    }

    // ========== CATEGORY MANAGEMENT ==========

    /**
     * Assign option to multiple categories
     *
     * Replaces: msOption::setCategories()
     *
     * Example:
     * $service->assignOptionToCategories(5, [10, 11, 12]);
     * // Assigns option #5 to categories 10, 11, 12
     *
     * @param int $optionId Option ID
     * @param array $categoryIds Array of category IDs
     * @return array Array of successfully assigned category IDs
     */
    public function assignOptionToCategories(int $optionId, array $categoryIds): array
    {
        return $this->category->assignToCategories($optionId, $categoryIds);
    }

    /**
     * Add option to category (triggers auto-assignment to products)
     *
     * Replaces: logic from msCategoryOption::save()
     *
     * Example:
     * $service->addOptionToCategory(5, 10, 'default', true, 0);
     * // Adds option #5 to category #10 and auto-assigns to all products
     *
     * @param int $optionId Option ID
     * @param int $categoryId Category ID
     * @param string $defaultValue Default value for products
     * @param bool $active Is option active
     * @param int $position Display position
     * @return bool Success status
     */
    public function addOptionToCategory(
        int $optionId,
        int $categoryId,
        string $defaultValue = '',
        bool $active = true,
        int $position = 0,
        ?string $caption = null,
        ?string $description = null
    ): bool {
        return $this->category->addToCategory(
            $optionId,
            $categoryId,
            $defaultValue,
            $active,
            $position,
            $caption,
            $description
        );
    }

    /**
     * Remove option from category (with smart data preservation)
     *
     * Replaces: logic from msCategoryOption::remove()
     * + ADDS check for other categories to prevent data loss
     *
     * IMPROVEMENT: Before deleting option values, checks if product has this option
     * active in other categories. Prevents data loss scenario:
     * - Product in categories A and B
     * - Option "Color" active in both
     * - Admin removes from category A
     * - WITHOUT check: Color values deleted (BAD!)
     * - WITH check: Color values preserved because active in B (GOOD!)
     *
     * Example:
     * $service->removeOptionFromCategory(5, 10);
     * // Removes option #5 from category #10, but preserves values if active elsewhere
     *
     * @param int $optionId Option ID
     * @param int $categoryId Category ID
     * @param bool $forceDelete Force delete values even if option active in other categories
     * @return bool Success status
     */
    public function removeOptionFromCategory(int $optionId, int $categoryId, bool $forceDelete = false): bool
    {
        return $this->category->removeFromCategory($optionId, $categoryId, $forceDelete);
    }

    /**
     * Get products in category (including additional categories)
     *
     * Replaces: msCategoryOption::getProductsInCategory()
     *
     * Example:
     * $products = $service->getProductsInCategory(10);
     * // Returns: [101, 102, 103, ...]
     *
     * @param int $categoryId Category ID
     * @return array Array of product IDs
     */
    public function getProductsInCategory(int $categoryId): array
    {
        return $this->category->getProductsInCategory($categoryId);
    }

    // ========== CONVENIENCE METHODS ==========

    /**
     * Get direct access to OptionLoaderService
     *
     * For advanced use cases that need direct access to loader
     *
     * @return OptionLoaderService
     */
    public function getLoader(): OptionLoaderService
    {
        return $this->loader;
    }

    /**
     * Get direct access to OptionSyncService
     *
     * For advanced use cases that need direct access to sync
     *
     * @return OptionSyncService
     */
    public function getSync(): OptionSyncService
    {
        return $this->sync;
    }

    /**
     * Get direct access to OptionCategoryService
     *
     * For advanced use cases that need direct access to category management
     *
     * @return OptionCategoryService
     */
    public function getCategory(): OptionCategoryService
    {
        return $this->category;
    }
}
