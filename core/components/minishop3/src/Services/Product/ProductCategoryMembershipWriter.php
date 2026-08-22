<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\Category\CategoryProductMenuindexService;
use MODX\Revolution\modX;

/**
 * Synchronizes msCategoryMember with product categories payload.
 */
class ProductCategoryMembershipWriter
{
    use ProductDataExplicitFieldsTrait;

    private CategoryProductMenuindexService $menuindexService;

    public function __construct(
        private modX $modx,
    ) {
        $this->menuindexService = new CategoryProductMenuindexService($modx);
    }

    /**
     * Save additional product categories.
     *
     * If `categories` was not sent, leave msCategoryMember untouched.
     * Preserves menuindex for kept pairs; new members get MAX(menuindex)+1 in that category.
     */
    public function saveCategories(msProductData $productData): void
    {
        $fields = $this->readExplicitFields($productData);
        if (!array_key_exists('categories', $fields)) {
            return;
        }

        $productId = (int) $productData->get('id');
        $desiredCategoryIds = $this->normalizeCategoryIds($fields['categories']);
        $desiredCategorySet = array_flip($desiredCategoryIds);

        /** @var array<int, msCategoryMember> $existingByCategory */
        $existingByCategory = [];
        /** @var msCategoryMember $member */
        foreach ($this->modx->getCollection(msCategoryMember::class, ['product_id' => $productId]) as $member) {
            $existingByCategory[(int) $member->get('category_id')] = $member;
        }

        foreach ($existingByCategory as $categoryId => $member) {
            if (!isset($desiredCategorySet[$categoryId])) {
                $member->remove();
            }
        }

        foreach ($desiredCategoryIds as $categoryId) {
            if (array_key_exists($categoryId, $existingByCategory)) {
                continue;
            }

            /** @var msCategoryMember $member */
            $member = $this->modx->newObject(msCategoryMember::class);
            $member->set('product_id', $productId);
            $member->set('category_id', $categoryId);
            $member->set('menuindex', $this->menuindexService->getNextMemberMenuindex($categoryId));
            $member->save();
        }
    }

    /**
     * @return list<int>
     */
    private function normalizeCategoryIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $categoryId) {
            if (empty($categoryId) || !is_numeric($categoryId)) {
                continue;
            }

            $ids[] = (int) $categoryId;
        }

        return array_values(array_unique($ids));
    }
}
