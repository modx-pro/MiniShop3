<?php

namespace MiniShop3\Processors\Gallery;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\Processors\ModelProcessor;

class Sort extends ModelProcessor
{
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
     *
     *
     * @return array|string
     */
    public function process()
    {
        /** @var msProductFile $source */
        $source = $this->modx->getObject(msProductFile::class, ['id' => $this->getProperty('source')]);
        /** @var msProductFile $target */
        $target = $this->modx->getObject(msProductFile::class, ['id' => $this->getProperty('target')]);
        /** @var msProductData $product */
        $product = $this->modx->getObject(msProductData::class, ['id' => $this->getProperty('product_id')]);
        $product_id = $product->get('id');

        if (empty($source) || empty($target) || empty($product_id)) {
            return $this->modx->error->failure();
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
