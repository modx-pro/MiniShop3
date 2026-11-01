<?php

namespace MiniShop3\Processors\Gallery;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    use GuardsProductDocumentTrait;

    public $classKey = msProductFile::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msproductfile_save';

    /**
     * Базовый RemoveProcessor проверяет checkPolicy('remove') только для
     * modAccessibleObject, а msProductFile им не является — поэтому по id файла
     * удалялся файл любого товара, включая закрытый группой ресурсов (#922).
     *
     * Проверка стоит в initialize(), а не в beforeRemove(): process() ниже
     * игнорирует результат parent::process() и всегда отвечает успехом.
     *
     * @return bool|string
     */
    public function initialize()
    {
        $initialized = parent::initialize();
        if ($initialized !== true) {
            return $initialized;
        }

        if (!$this->fileDocumentAllowed($this->object)) {
            return $this->productDocumentDeniedMessage();
        }

        return true;
    }

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
