<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MODX\Revolution\Processors\Processor;
use MODX\Revolution\Sources\modMediaSource;

class UpdateSource extends Processor
{
    /** @var string */
    public $permission = 'msproduct_save';
    /** @var array<string> */
    public $languageTopics = ['minishop3:default', 'minishop3:product'];

    public function process(): array
    {
        $id = (int) $this->getProperty('id', 0);
        if ($id <= 0) {
            return $this->failure($this->modx->lexicon('ms3_err_ns'));
        }

        /** @var msProduct|null $product */
        $product = $this->modx->getObject(msProduct::class, $id);
        if (!$product) {
            return $this->failure($this->modx->lexicon('ms3_err_nf'));
        }

        if (!$product->checkPolicy('save')) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $sourceId = (int) $this->getProperty('source_id', 0);
        if ($sourceId <= 0) {
            return $this->failure($this->modx->lexicon('ms3_err_ns'));
        }

        /** @var modMediaSource|null $source */
        $source = $this->modx->getObject(modMediaSource::class, $sourceId);
        if (!$source) {
            return $this->failure($this->modx->lexicon('ms3_err_nf'));
        }

        if (!$source->checkPolicy('view')) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        /** @var msProductData|null $productData */
        $productData = $product->loadData();
        if (!$productData) {
            return $this->failure($this->modx->lexicon('ms3_err_nf'));
        }

        $productData->set('source_id', $sourceId);
        if (!$productData->save()) {
            return $this->failure($this->modx->lexicon('ms3_err_save'));
        }

        return $this->success();
    }
}
