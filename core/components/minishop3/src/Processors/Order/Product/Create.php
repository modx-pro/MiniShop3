<?php

namespace MiniShop3\Processors\Order\Product;

use MODX\Revolution\Processors\Model\CreateProcessor;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderStatus;

class Create extends CreateProcessor
{
    public $classKey = msOrderProduct::class;
    public $objectType = 'msOrderProduct';
    public $languageTopics = ['minishop3'];
    public $beforeSaveEvent = 'msOnBeforeCreateOrderProduct';
    public $afterSaveEvent = 'msOnCreateOrderProduct';
    public $permission = 'msorder_save';
    /** @var msOrder $order */
    protected $order;


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
     * @return bool|null|string
     */
    public function beforeSet()
    {
        $count = $this->getProperty('count');
        if ($count <= 0) {
            $this->modx->error->addField('count', $this->modx->lexicon('ms3_err_ns'));
        }

        $options = $this->getProperty('options');
        if (!empty($options)) {
            $tmp = json_decode($options, true);
            if (!is_array($tmp)) {
                $this->modx->error->addField('options', $this->modx->lexicon('ms3_err_json'));
            } else {
                $this->setProperty('options', $tmp);
            }
        }

        $this->order = $this->modx->getObject(msOrder::class, ['id' => $this->getProperty('order_id')]);
        if (!$this->order) {
            return $this->modx->lexicon('ms3_err_order_nf');
        }

        /** @var msOrderStatus $status */
        $status = $this->order->getOne('Status');
        if ($status && $status->get('final')) {
            return $this->modx->lexicon('ms3_err_status_final');
        }

        $this->setProperty('cost', $this->getProperty('price') * $this->getProperty('count'));
        $this->setProperty('product_id', $this->getProperty('id'));

        return !$this->hasErrors();
    }


    /**
     * @return bool
     */
    public function beforeSave()
    {
        $this->object->fromArray([
            'position' => $this->modx->getCount(msOrderProduct::class),
        ]);

        return parent::beforeSave();
    }


    /**
     *
     */
    public function afterSave()
    {
        // Fix "cache"
        $this->order = $this->modx->getObject(msOrder::class, $this->order->id, false);
        if ($this->order) {
            $this->order->updateProducts();
        }
    }
}
