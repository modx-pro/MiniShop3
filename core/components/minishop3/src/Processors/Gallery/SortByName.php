<?php

namespace MiniShop3\Processors\Gallery;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Services\Product\ProductImageService;
use MODX\Revolution\Processors\ModelProcessor;

/**
 * Re-rank gallery images by natural sort on filename after batch upload (#616).
 */
class SortByName extends ModelProcessor
{
    public $classKey = msProductFile::class;
    public $languageTopics = ['minishop3:default', 'minishop3:product'];
    public $permission = 'msproductfile_save';

    public function process()
    {
        $productId = (int) $this->getProperty('product_id');

        if ($productId <= 0) {
            return $this->failure($this->modx->lexicon('ms3_gallery_err_ns'));
        }

        /** @var msProductData|null $productData */
        $productData = $this->modx->getObject(msProductData::class, ['id' => $productId]);
        if (!$productData) {
            return $this->failure($this->modx->lexicon('ms3_gallery_err_no_product'));
        }

        /** @var ProductImageService|null $imageService */
        $imageService = $this->modx->services->get('ms3_product_image');
        if (!$imageService instanceof ProductImageService) {
            return $this->failure($this->modx->lexicon('ms3_err_unknown'));
        }

        $saved = $imageService->sortProductImagesByName($productData);
        if ($saved === false) {
            return $this->failure($this->modx->lexicon('ms3_err_unknown'));
        }

        $thumb = (string) $productData->get('thumb');
        if ($thumb === '') {
            /** @var MiniShop3 $ms3 */
            $ms3 = $this->modx->services->get('ms3');
            $thumb = (string) ($ms3->config['defaultThumb'] ?? '');
        }

        return $this->success('', [
            'thumb' => $thumb,
        ]);
    }
}
