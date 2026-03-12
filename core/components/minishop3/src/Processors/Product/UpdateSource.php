<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msProduct;
use MODX\Revolution\Processors\Processor;
use MODX\Revolution\Sources\modMediaSource;

/**
 * Updates only the media source (source_id) of a product.
 * Used by the gallery UI to change the file source without submitting the full product form.
 */
class UpdateSource extends Processor
{
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msproduct_save';

    /** @var msProduct|null */
    protected $product;

    /**
     * @return bool|string
     */
    public function initialize()
    {
        $id = (int)$this->getProperty('id');
        if ($id <= 0) {
            return $this->modx->lexicon('invalid_data');
        }

        $this->product = $this->modx->getObject(msProduct::class, $id);
        if (!$this->product) {
            return $this->modx->lexicon('resource_err_nfs', ['id' => $id]);
        }

        if (!$this->product->checkPolicy('save')) {
            return $this->modx->lexicon('access_denied');
        }

        return parent::initialize();
    }

    /**
     * @return array|string
     */
    public function process()
    {
        $sourceId = (int)$this->getProperty('source_id');
        if ($sourceId <= 0) {
            return $this->failure($this->modx->lexicon('invalid_data'));
        }

        $source = $this->modx->getObject(modMediaSource::class, $sourceId);
        if (!$source || !$source->checkPolicy('view')) {
            return $this->failure($this->modx->lexicon('invalid_data'));
        }

        $this->product->set('source_id', $sourceId);
        if (!$this->product->save()) {
            return $this->failure($this->modx->lexicon('ms3_err_save'));
        }

        return $this->success();
    }
}
