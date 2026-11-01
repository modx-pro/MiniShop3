<?php

namespace MiniShop3\Processors\Gallery;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\Processors\ModelProcessor;

class Sort extends ModelProcessor
{
    use GuardsProductDocumentTrait;

    public $classKey = msProductFile::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msproductfile_save';

    /**
     * @return array|string
     */
    public function process()
    {
        $productId = (int) $this->getProperty('product_id');

        // msProductData — обычный объект xPDO, ACL документа на нём не работает (#922).
        if (!$this->productDocumentAllowed($productId)) {
            return $this->modx->error->failure($this->productDocumentDeniedMessage());
        }

        /** @var msProductFile $source */
        $source = $this->modx->getObject(msProductFile::class, ['id' => $this->getProperty('source_id')]);
        /** @var msProductFile $target */
        $target = $this->modx->getObject(msProductFile::class, ['id' => $this->getProperty('target_id')]);
        /** @var msProductData|null $product */
        $product = $this->modx->getObject(msProductData::class, ['id' => $productId]);

        // Раньше ->get('id') звался до проверки на null и падал на отсутствующем товаре.
        if (empty($source) || empty($target) || !$product) {
            return $this->modx->error->failure();
        }

        $product_id = (int) $product->get('id');

        // Оба файла обязаны принадлежать этому товару: иначе чужим файлом
        // можно было сдвинуть позиции в галерее указанного товара.
        if (
            (int) $source->get('product_id') !== $product_id
            || (int) $target->get('product_id') !== $product_id
        ) {
            return $this->modx->error->failure($this->modx->lexicon('ms3_gallery_err_file_not_in_product'));
        }

        if ($source->get('position') < $target->get('position')) {
            $sql = "UPDATE {$this->modx->getTableName(msProductFile::class)}
                SET `position` = `position` - 1 WHERE
                    `product_id` = {$product_id}
                    AND `position` <= {$target->get('position')}
                    AND `position` > {$source->get('position')}
                    AND `position` > 0
            ";
        } else {
            $sql = "UPDATE {$this->modx->getTableName(msProductFile::class)}
                SET `position` = `position` + 1 WHERE
                    `product_id` = {$product_id}
                    AND `position` >= {$target->get('position')}
                    AND `position` < {$source->get('position')}
            ";
        }
        $this->modx->exec($sql);
        $newPosition = $target->get('position');
        $source->set('position', $newPosition);
        $source->save();

        $thumb = $product->updateProductImage();

        return $this->modx->error->success('', ['thumb' => $thumb]);
    }
}
