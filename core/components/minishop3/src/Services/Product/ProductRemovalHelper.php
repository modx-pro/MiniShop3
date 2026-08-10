<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Model\msProductLink;
use MiniShop3\Model\msProductOption;
use MODX\Revolution\modX;

/**
 * Removes product-related rows and media when a product is deleted.
 */
class ProductRemovalHelper
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * @param list<int|string> $ancestors
     */
    public function removeProduct(msProductData $productData, array $ancestors = []): bool
    {
        $productId = (int) $productData->get('id');

        $this->modx->removeCollection(msProductOption::class, ['product_id' => $productId]);
        $this->modx->removeCollection(msCategoryMember::class, ['product_id' => $productId]);
        $this->modx->removeCollection(msProductLink::class, [
            'master' => $productId,
            'OR:slave:=' => $productId,
        ]);

        $this->removeProductFiles($productData, $productId);

        /** @var ProductImageService|null $imageService */
        $imageService = $this->modx->services->get('ms3_product_image');
        if ($imageService) {
            $imageService->removeProductCatalog($productData);
        }

        return true;
    }

    private function removeProductFiles(msProductData $productData, int $productId): void
    {
        if ($productData->xpdo->getCount(msProductFile::class, ['product_id' => $productId]) < 1) {
            return;
        }

        $product = $productData->getOne('Product');
        if (!$product) {
            return;
        }

        $source = $productData->initializeMediaSource($product->get('context_key'));
        if (!$source) {
            return;
        }

        /** @var msProductFile $file */
        foreach ($productData->xpdo->getIterator(msProductFile::class, ['product_id' => $productId]) as $file) {
            $file->remove();
        }
    }
}
