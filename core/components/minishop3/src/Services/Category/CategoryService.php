<?php

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Service for working with categories
 *
 * Extracts business logic from msCategory model into separate service
 * for improved testability and separation of concerns
 */
class CategoryService
{
    /** @var modX */
    protected $modx;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Handle category save
     *
     * If category changes type (was not msCategory, became msCategory),
     * show all child products in tree
     *
     * @param msCategory $category
     * @param string $oldClassKey Old class_key before save
     * @return bool
     */
    public function handleCategorySave(msCategory $category, string $oldClassKey): bool
    {
        if (!$category->isNew() && $oldClassKey !== 'msCategory') {
            $category->set('hide_children_in_tree', false);

            $this->showChildProductsInTree($category->get('id'));
        }

        return true;
    }

    /**
     * Show category child products in tree
     *
     * @param int $categoryId
     * @return bool
     */
    public function showChildProductsInTree(int $categoryId): bool
    {
        /** @var xPDOQuery $query */
        $query = $this->modx->newQuery(msProduct::class);
        $query->command('UPDATE');
        $query->where([
            'parent' => $categoryId,
            'class_key' => 'msProduct',
        ]);
        $query->set([
            'show_in_tree' => true,
        ]);

        if ($query->prepare()) {
            return $query->stmt->execute();
        }

        return false;
    }

    /**
     * Duplicate category with all related data
     *
     * @param msCategory $category Source category
     * @param msCategory $newCategory New category (already duplicated by parent)
     * @return msCategory
     */
    public function duplicateCategory(msCategory $category, msCategory $newCategory): msCategory
    {
        $this->duplicateCategoryOptions($category, $newCategory);

        return $newCategory;
    }

    /**
     * Copy category options
     *
     * @param msCategory $sourceCategory
     * @param msCategory $targetCategory
     * @return bool
     */
    protected function duplicateCategoryOptions(msCategory $sourceCategory, msCategory $targetCategory): bool
    {
        $options = $sourceCategory->getMany('CategoryOptions');

        /** @var msCategoryOption $option */
        foreach ($options as $option) {
            /** @var msCategoryOption $newOption */
            $newOption = $this->modx->newObject(msCategoryOption::class);
            $newOption->fromArray($option->toArray(), '', true, true);
            $newOption->set('category_id', $targetCategory->get('id'));

            if (!$newOption->save()) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[CategoryService] Failed to duplicate category option: ' . print_r($option->toArray(), true)
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Get neighbor categories (left and right)
     *
     * Used for navigation between categories of the same level
     *
     * @param msCategory $category
     * @return array ['left' => [id1, id2, ...], 'right' => [id3, id4, ...]]
     */
    public function getNeighborCategories(msCategory $category): array
    {
        $query = $this->modx->newQuery(msCategory::class, [
            'parent' => $category->get('parent'),
            'class_key' => 'msCategory'
        ]);
        $query->sortby('menuindex', 'ASC');
        $query->select('id');

        if (!$query->prepare() || !$query->stmt->execute()) {
            return ['left' => [], 'right' => []];
        }

        $ids = $query->stmt->fetchAll(\PDO::FETCH_COLUMN);
        $currentIndex = array_search($category->get('id'), $ids);

        if ($currentIndex === false) {
            return ['left' => [], 'right' => []];
        }

        $left = [];
        $right = [];

        foreach ($ids as $index => $id) {
            if ($index < $currentIndex) {
                $left[] = $id;
            } elseif ($index > $currentIndex) {
                $right[] = $id;
            }
        }

        return [
            'left' => array_reverse($left),
            'right' => $right,
        ];
    }
}
