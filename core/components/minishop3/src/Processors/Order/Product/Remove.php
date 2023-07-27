<?php

namespace MiniShop3\Processors\Order\Product;

use MODX\Revolution\Processors\Model\RemoveProcessor;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;

class Remove extends RemoveProcessor
{
    public $classKey = msOrderProduct::class;
    public $objectType = 'msOrderProduct';
    public $languageTopics = ['minishop3'];
    public $beforeRemoveEvent = 'msOnBeforeRemoveOrderProduct';
    public $afterRemoveEvent = 'msOnRemoveOrderProduct';
    public $permission = 'msorder_save';
    /** @var msOrder $order */
    protected $order;


    /**
     * @return bool|null|string
     */
    public function beforeRemove()
    {
        $this->order = $this->object->getOne('Order');
        if (!$this->order) {
            return $this->modx->lexicon('ms3_err_order_nf');
        }

        $status = $this->order->getOne('Status');
        if ($status && $status->get('final')) {
            return $this->modx->lexicon('ms3_err_status_final');
        }

        $this->setProperty('cost', $this->getProperty('price') * $this->getProperty('count'));

        return !$this->hasErrors();
    }


    /**
     *
     */
    public function afterRemove()
    {
        // Fix "cache"
        $this->order = $this->modx->getObject(msOrder::class, $this->order->get('id'), false);
        if ($this->order) {
            $this->order->updateProducts();
        }
    }
}
