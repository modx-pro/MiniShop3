<?php

namespace MiniShop3\Processors\Settings\Payment\Deliveries;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msPayment;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Disable extends RemoveProcessor
{
    /** @var msDelivery $object */
    public $object;
    public $classKey = msDeliveryMember::class;
    public $languageTopics = ['minishop'];
    public $permission = 'mssetting_save';


    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }

        /**
         * @TODO: hardcode
         */
        $this->object = $this->modx->getObject($this->classKey, $this->getProperties());
        if (empty($this->object)) {
            return $this->modx->lexicon($this->objectType . '_err_nfs');
        }

        return true;
    }


    /**
     * @return bool
     */
    public function beforeSave()
    {
        $this->object->fromArray($this->getProperties(), '', true, true);

        return true;
    }
}
