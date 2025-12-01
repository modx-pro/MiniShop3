<?php

namespace MiniShop3\Processors\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MODX\Revolution\Processors\Model\UpdateProcessor;
use MODX\Revolution\Validation\modValidator;

class Update extends UpdateProcessor
{
    public $classKey = msOrder::class;
    public $objectType = 'msOrder';
    public $languageTopics = ['minishop3:default'];
    public $beforeSaveEvent = 'msOnBeforeUpdateOrder';
    public $afterSaveEvent = 'msOnUpdateOrder';
    public $permission = 'msorder_save';
    protected $status_id;
    protected $delivery_id;
    protected $payment_id;

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
     * {@inheritDoc}
     * @return mixed
     */
    public function process()
    {
        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');

        /* Run the beforeSet method before setting the fields, and allow stoppage */
        $canSave = $this->beforeSet();
        if ($canSave !== true) {
            return $this->failure($canSave);
        }

        $this->object->fromArray($this->getProperties());

        /* Run the beforeSave method and allow stoppage */
        $canSave = $this->beforeSave();
        if ($canSave !== true) {
            return $this->failure($canSave);
        }

        /* run object validation */
        if (!$this->object->validate()) {
            /** @var modValidator $validator */
            $validator = $this->object->getValidator();
            if ($validator->hasMessages()) {
                foreach ($validator->getMessages() as $message) {
                    $this->addFieldError($message['field'], $this->modx->lexicon($message['message']));
                }
            }
        }

        /* run the before save event and allow stoppage */
        $preventSave = $this->fireBeforeSaveEvent();
        if (!empty($preventSave)) {
            return $this->failure($preventSave);
        }

        if ($this->saveObject() == false) {
            return $this->failure($this->modx->lexicon($this->objectType . '_err_save'));
        }

        // set "new status" via OrderStatus controller
        if ($this->object->get('status_id') != $this->status_id) {
            $orderStatusController = new \MiniShop3\Controllers\Order\OrderStatus($ms3);
            $change_status = $orderStatusController->change($this->object->get('id'), $this->status_id);
            if ($change_status !== true) {
                return $this->failure($change_status);
            }
            $this->object = $this->modx->getObject($this->classKey, $this->object->get('id'), false);
        }

        $this->afterSave();
        $this->fireAfterSaveEvent();
        $this->logManagerAction();

        return $this->cleanup();
    }

    /**
     * @return bool|null|string
     */
    public function beforeSet()
    {
        foreach (['status_id', 'delivery_id', 'payment_id'] as $v) {
            if (!$this->$v = $this->getProperty($v)) {
                $this->addFieldError($v, $this->modx->lexicon('ms3_err_ns'));
            }
        }

        // Check if current order status is final (cannot modify orders with final status)
        if ($status = $this->modx->getObject(msOrderStatus::class, ['id' => $this->object->get('status_id')])) {
            if ($status->get('final')) {
                return $this->modx->lexicon('ms3_err_status_final');
            }
        }
        // set "old status"
        $this->setProperty('status_id', $this->object->get('status_id'));

        return parent::beforeSet();
    }

    /**
     * @return bool|string
     */
    public function beforeSave()
    {
        $this->object->set('updatedon', time());

        if ($address = $this->object->getOne('Address')) {
            foreach ($this->getProperties() as $k => $v) {
                if (strpos($k, 'addr_') !== false) {
                    $address->set(substr($k, 5), $v);
                }
            }
            $this->object->addOne($address, 'Address');
        }

        return parent::beforeSave();
    }
}
