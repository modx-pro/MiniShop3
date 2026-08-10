<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProductData;
use MODX\Revolution\modX;

/**
 * Synchronizes msCategoryMember with product categories payload.
 */
class ProductCategoryMembershipWriter
{
    use ProductDataExplicitFieldsTrait;

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Save additional product categories.
     *
     * If `categories` was not sent, leave msCategoryMember untouched.
     */
    public function saveCategories(msProductData $productData): void
    {
        $fields = $this->readExplicitFields($productData);
        if (!array_key_exists('categories', $fields)) {
            return;
        }

        $productId = $productData->get('id');
        $categories = $this->normalizeList($fields['categories']);

        $this->modx->removeCollection(msCategoryMember::class, ['product_id' => $productId]);

        foreach ($categories as $categoryId) {
            if (empty($categoryId) || !is_numeric($categoryId)) {
                continue;
            }

            /** @var msCategoryMember $member */
            $member = $this->modx->newObject(msCategoryMember::class);
            $member->set('product_id', $productId);
            $member->set('category_id', (int)$categoryId);
            $member->save();
        }
    }

    /**
     * @return list<mixed>
     */
    private function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }
}
