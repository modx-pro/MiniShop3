<?php

namespace MiniShop3\Processors\Settings\Delivery;

use MiniShop3\Model\msDelivery;
use MiniShop3\Utils\PriceAdjustment;
use MODX\Revolution\Processors\Model\CreateProcessor;

class Create extends CreateProcessor
{
    /** @var msDelivery $object */
    public $object;
    public $classKey = msDelivery::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_save';


    /**
     * @return bool
     */
    public function beforeSet()
    {
        $required = ['name'];
        foreach ($required as $field) {
            if (!$tmp = trim($this->getProperty($field))) {
                $this->addFieldError($field, $this->modx->lexicon('field_required'));
            } else {
                $this->setProperty($field, $tmp);
            }
        }
        if ($this->modx->getCount($this->classKey, ['name' => $this->getProperty('name')])) {
            $this->modx->error->addField('name', $this->modx->lexicon('ms3_err_ae'));
        }

        $prices = ['price', 'distance_price', 'weight_price', 'free_delivery_amount'];
        foreach ($prices as $field) {
            $this->setProperty($field, PriceAdjustment::normalize($this->getProperty($field, 0)));
        }

        return !$this->hasErrors();
    }


    /**
     * @return bool
     */
    public function beforeSave()
    {
        $this->object->fromArray([
            'position' => $this->modx->getCount($this->classKey),
        ]);

        return parent::beforeSave();
    }
}
