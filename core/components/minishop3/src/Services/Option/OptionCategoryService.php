<?php

namespace MiniShop3\Services\Option;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductOption;
use xPDO\xPDO;

/**
 * Service for managing option-category relationships
 *
 * Extracted from msOption and msCategoryOption models
 * Handles:
 * - Assigning options to categories
 * - Auto-assignment to products when option added to category
 * - Smart removal (prevents data loss when product is in multiple categories)
 */
class OptionCategoryService
{
    /** @var xPDO */
    protected $xpdo;

    /**
     * @param xPDO $xpdo
     */
    public function __construct(xPDO $xpdo)
    {
        $this->xpdo = $xpdo;
    }

    /**
     * Assign option to multiple categories
     *
     * Replaces: msOption::setCategories()
     *
     * @param int $optionId Option ID
     * @param array $categoryIds Array of category IDs
     * @return array Array of successfully assigned category IDs
     */
    public function assignToCategories(int $optionId, array $categoryIds): array
    {
        if (empty($categoryIds) || !is_array($categoryIds)) {
            return [];
        }

        // Check which categories actually exist
        $existingCategories = $this->xpdo->getCollection(
            msCategory::class,
            ['id:IN' => $categoryIds]
        );

        if (empty($existingCategories)) {
            return [];
        }

        $existingCategoryIds = array_keys($existingCategories);

        // Check existing links
        $existingLinks = $this->xpdo->getCollection(
            msCategoryOption::class,
            [
                'category_id:IN' => $existingCategoryIds,
                'option_id' => $optionId
            ]
        );

        $existingLinkIds = [];
        foreach ($existingLinks as $link) {
            $existingLinkIds[] = $link->get('category_id');
        }

        // Create new links only for categories that don't have them yet
        $newCategoryIds = array_diff($existingCategoryIds, $existingLinkIds);

        if (!empty($newCategoryIds)) {
            foreach ($newCategoryIds as $categoryId) {
                $this->addToCategory($optionId, $categoryId);
            }
        }

        return $existingCategoryIds;
    }

    /**
     * Add option to category with auto-assignment to products
     *
     * Replaces logic from: msCategoryOption::save()
     *
     * @param int $optionId Option ID
     * @param int $categoryId Category ID
     * @param string $defaultValue Default value for products
     * @param bool $active Is option active
     * @param int $position Display position
     * @return bool Success status
     */
    public function addToCategory(
        int $optionId,
        int $categoryId,
        string $defaultValue = '',
        bool $active = true,
        int $position = 0
    ): bool {
        // Check if link already exists
        $existing = $this->xpdo->getObject(msCategoryOption::class, [
            'option_id' => $optionId,
            'category_id' => $categoryId
        ]);

        if ($existing) {
            // Update existing link
            $existing->set('value', $defaultValue);
            $existing->set('active', $active);
            $existing->set('position', $position);
            return $existing->save();
        }

        // Create new link
        $link = $this->xpdo->newObject(msCategoryOption::class);
        $link->set('option_id', $optionId);
        $link->set('category_id', $categoryId);
        $link->set('value', $defaultValue);
        $link->set('active', $active);
        $link->set('position', $position);

        // Auto-assignment to products will be triggered by lifecycle hook save()
        return $link->save();
    }

    /**
     * Remove option from category (IMPROVED with data preservation)
     *
     * Replaces logic from: msCategoryOption::remove()
     * + ADDS smart check for other categories to prevent data loss
     *
     * @param int $optionId Option ID
     * @param int $categoryId Category ID
     * @param bool $forceDelete Force delete values even if option active in other categories
     * @return bool Success status
     */
    public function removeFromCategory(int $optionId, int $categoryId, bool $forceDelete = false): bool
    {
        // Remove the link
        $link = $this->xpdo->getObject(msCategoryOption::class, [
            'option_id' => $optionId,
            'category_id' => $categoryId
        ]);

        if (!$link) {
            return true; // Already removed
        }

        // Get products in category
        $productIds = $this->getProductsInCategory($categoryId);

        if (!empty($productIds)) {
            $option = $this->xpdo->getObject(msOption::class, $optionId);
            $key = $option->get('key');

            // ✅ IMPROVEMENT: Check each product if option is active in other categories
            foreach ($productIds as $productId) {
                $shouldDelete = $forceDelete;

                if (!$forceDelete) {
                    // Check if this option is active in other categories of this product
                    $otherCategories = $this->getOtherCategoriesWithOption($productId, $optionId, $categoryId);
                    $shouldDelete = empty($otherCategories);
                }

                // Delete option value only if not active in other categories
                if ($shouldDelete) {
                    $this->xpdo->removeCollection(msProductOption::class, [
                        'product_id' => $productId,
                        'key' => $key
                    ]);
                }
            }
        }

        // Remove the link
        return $link->remove();
    }

    /**
     * Check if product has this option active in other categories
     *
     * NEW METHOD - prevents data loss when removing option from one category
     *
     * Example scenario:
     * - Product "iPhone 15" is in categories: "Smartphones" and "Apple"
     * - Option "Color" is active in BOTH categories
     * - Admin removes "Color" from "Smartphones"
     * - WITHOUT this check: Color values would be deleted from iPhone
     * - WITH this check: Color values preserved because option still active in "Apple"
     *
     * @param int $productId Product ID
     * @param int $optionId Option ID
     * @param int $excludeCategoryId Category ID to exclude from check
     * @return array Array of category IDs where option is still active
     */
    public function getOtherCategoriesWithOption(int $productId, int $optionId, int $excludeCategoryId): array
    {
        // Get all categories of this product
        $productCategories = [];

        // Direct parent category
        $product = $this->xpdo->getObject(msProduct::class, $productId);
        if ($product) {
            $productCategories[] = $product->get('parent');
        }

        // Additional categories
        $q = $this->xpdo->newQuery(msCategoryMember::class, ['product_id' => $productId]);
        $q->select('category_id');
        if ($q->prepare() && $q->stmt->execute()) {
            $additionalCategories = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
            $productCategories = array_merge($productCategories, $additionalCategories);
        }

        $productCategories = array_unique($productCategories);

        // Remove the category we're removing option from
        $productCategories = array_diff($productCategories, [$excludeCategoryId]);

        if (empty($productCategories)) {
            return [];
        }

        // Check if option is active in any of these categories
        $c = $this->xpdo->newQuery(msCategoryOption::class);
        $c->where([
            'option_id' => $optionId,
            'category_id:IN' => $productCategories,
            'active' => 1
        ]);
        $c->select('category_id');

        if ($c->prepare() && $c->stmt->execute()) {
            return $c->stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        return [];
    }

    /**
     * Get all products in category (including additional categories)
     *
     * Replaces: msCategoryOption::getProductsInCategory()
     *
     * @param int $categoryId Category ID
     * @return array Array of product IDs
     */
    public function getProductsInCategory(int $categoryId): array
    {
        $productIds = [];

        // Products with direct parent
        $q = $this->xpdo->newQuery(msProduct::class, ['parent' => $categoryId]);
        $q->select('id');

        if ($q->prepare() && $q->stmt->execute()) {
            $productIds = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        // Products from additional categories (ms3_category_members)
        $q = $this->xpdo->newQuery(msCategoryMember::class, ['category_id' => $categoryId]);
        $q->select('product_id');

        if ($q->prepare() && $q->stmt->execute()) {
            $additionalIds = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
            $productIds = array_merge($productIds, $additionalIds);
        }

        return array_unique($productIds);
    }

    /**
     * Get products that already have specific option
     *
     * Replaces: msCategoryOption::getProductsWithOption()
     *
     * @param array $productIds Product IDs to check
     * @param string $key Option key
     * @return array Array of product IDs that have this option
     */
    public function getProductsWithOption(array $productIds, string $key): array
    {
        $q = $this->xpdo->newQuery(msProductOption::class, [
            'product_id:IN' => $productIds,
            'key' => $key
        ]);
        $q->select('product_id');

        if ($q->prepare() && $q->stmt->execute()) {
            return array_unique($q->stmt->fetchAll(\PDO::FETCH_COLUMN));
        }

        return [];
    }

    /**
     * Batch INSERT options for multiple products
     *
     * Replaces: msCategoryOption::batchInsertOptions()
     * Uses proper prepared statements (SQL Injection fix applied)
     *
     * @param array $productIds Array of product IDs
     * @param string $key Option key
     * @param mixed $value Option value
     * @return bool Success status
     */
    public function batchInsertOptions(array $productIds, string $key, $value): bool
    {
        if (empty($productIds)) {
            return false;
        }

        $tableName = $this->xpdo->getTableName(msProductOption::class);

        // Build placeholders (?, ?, ?) for each product
        $placeholders = [];
        $params = [];

        foreach ($productIds as $productId) {
            $placeholders[] = '(?, ?, ?)';
            $params[] = (int)$productId;
            $params[] = $key;
            $params[] = $value;
        }

        // Build SQL with placeholders
        $sql = "INSERT INTO {$tableName} (`product_id`, `key`, `value`) VALUES "
             . implode(',', $placeholders);

        try {
            $stmt = $this->xpdo->prepare($sql);
            $result = $stmt->execute($params);
            $stmt->closeCursor();
            return $result;
        } catch (\Exception $e) {
            $this->xpdo->log(
                \modX::LOG_LEVEL_ERROR,
                '[OptionCategoryService::batchInsertOptions] Error: ' . $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Remove option from multiple categories
     *
     * Replaces SQL logic from: Settings\Option\Update::removeNotAssignedCategories()
     * Batch removal of msCategoryOption links
     *
     * @param int $optionId Option ID
     * @param array $categoryIds Array of category IDs to remove from
     * @return bool Success status
     */
    public function removeFromCategories(int $optionId, array $categoryIds): bool
    {
        if (empty($categoryIds)) {
            return true;
        }

        // Remove links using xPDO (safer than raw SQL)
        $removed = $this->xpdo->removeCollection(msCategoryOption::class, [
            'option_id' => $optionId,
            'category_id:IN' => $categoryIds
        ]);

        return $removed !== false;
    }
}
