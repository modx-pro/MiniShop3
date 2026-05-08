<?php

namespace MiniShop3\Processors\Settings\Delivery;

use MiniShop3\Model\msDelivery;
use MiniShop3\Utils\PriceAdjustment;
use MODX\Revolution\Processors\Model\UpdateProcessor;

class Update extends UpdateProcessor
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
        $name = $this->getProperty('name');
        $count = $this->modx->getCount(
            $this->classKey,
            ['name' => $name, 'id:!=' => $this->object->get('id')]
        );
        if (!empty($count)) {
            $this->modx->error->addField('name', $this->modx->lexicon('ms3_err_ae'));
        }

        $prices = ['price', 'distance_price', 'weight_price', 'free_delivery_amount'];
        foreach ($prices as $field) {
            $price = $this->getProperty($field);
            if ($price !== null) {
                $this->setProperty($field, PriceAdjustment::normalize($price));
            }
        }

        return !$this->hasErrors();
    }
}
