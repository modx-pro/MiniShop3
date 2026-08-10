<?php

namespace MiniShop3\Processors\Gallery;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msProductFile::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msproductfile_save';

    /**
     * @return array|string
     */
    public function process()
    {
        parent::process();

        /** @var msProduct $product */
        $product = $this->object->getOne('Product');
        $thumb = '';
        if ($product) {
            $productData = $product->getOne('Data');

            if ($productData) {
                /** @var \MiniShop3\Services\Product\ProductImageService $imageService */
                $imageService = $this->modx->services->get('ms3_product_image');
                if ($imageService) {
                    $imageService->updateProductImage($productData);
                    $thumb = $productData->get('thumb');
                }

                if (empty($product->getMany('Files')) && $imageService) {
                    $imageService->removeProductCatalog($productData);
                }
            }
        }

        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');
        if (empty($thumb)) {
            $thumb = $ms3->config['defaultThumb'];
        }

        return $this->success('', ['thumb' => $thumb]);
    }
}
