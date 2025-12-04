<?php

namespace MiniShop3\Processors\Gallery;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\Processors\ModelProcessor;

class GenerateAll extends  ModelProcessor
{
    public $classKey = msProductFile::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msproductfile_generate';


    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }

        return parent::initialize();
    }


    /**
     * @return array|string
     */
    public function process()
    {
        $product_id = (int)$this->getProperty('product_id');
        if (empty($product_id)) {
            return $this->failure($this->modx->lexicon('ms3_gallery_err_ns'));
        }

        /** @var msProductData $productData */
        $productData = $this->modx->getObject(msProductData::class, ['id' => $product_id]);
        if (!$productData) {
            return $this->failure($this->modx->lexicon('ms3_gallery_err_no_product'));
        }

        $files = $this->modx->getCollection(msProductFile::class, ['product_id' => $product_id, 'parent_id' => 0]);
        /** @var msProductFile $file */
        foreach ($files as $file) {
            $children = $file->getMany('Children');
            /** @var msProductFile $child */
            foreach ($children as $child) {
                $child->remove();
            }
        }

        /** @var \MiniShop3\Services\Product\ProductImageService $imageService */
        $imageService = $this->modx->services->get('ms3_product_image');
        if ($imageService) {
            $imageService->generateAllThumbnails($productData);
            $imageService->updateProductImage($productData);
        }

        $thumb = $productData->get('thumb');
        if (empty($thumb)) {
            /** @var MiniShop3 $ms3 */
            $ms3 = $this->modx->services->get('ms3');
            $thumb = $ms3->config['defaultThumb'];
        }

        return $this->success('', ['thumb' => $thumb]);
    }
}
