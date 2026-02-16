<?php

namespace MiniShop3\Processors\Gallery;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\Processors\ModelProcessor;

class Generate extends ModelProcessor
{
    public $classKey = msProductFile::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msproductfile_generate';


    /**
     * @return array|string
     */
    public function process()
    {
        $id = (int)$this->getProperty('id');
        if (empty($id)) {
            return $this->failure($this->modx->lexicon('ms3_gallery_err_ns'));
        }

        /** @var msProductFile $file */
        $file = $this->modx->getObject(msProductFile::class, $id);
        if ($file) {
            $children = $file->getMany('Children');
            /** @var msProductFile $child */
            foreach ($children as $child) {
                $child->remove();
            }
            $file->generateThumbnails();

            /** @var msProductData $productData */
            $productData = $this->modx->getObject(msProductData::class, ['id' => $file->get('product_id')]);
            if ($productData) {
                /** @var \MiniShop3\Services\Product\ProductImageService $imageService */
                $imageService = $this->modx->services->get('ms3_product_image');
                if ($imageService) {
                    $imageService->updateProductImage($productData);
                }
            }

            return $this->success();
        }

        return $this->success();
    }
}
