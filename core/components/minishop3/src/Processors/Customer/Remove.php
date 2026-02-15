<?php

namespace MiniShop3\Processors\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerAddress;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msCustomer::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msorder_remove';
    public int $customerId = 0;

    public function process()
    {
        $canRemove = $this->beforeRemove();
        if ($canRemove !== true) {
            return $this->failure($canRemove);
        }
        $preventRemoval = $this->fireBeforeRemoveEvent();
        if (!empty($preventRemoval)) {
            return $this->failure($preventRemoval);
        }

        $this->customerId = $this->object->get('id');

        if ($this->removeObject() === false) {
            return $this->failure($this->modx->lexicon($this->objectType . '_err_remove'));
        }
        $this->afterRemove();
        $this->fireAfterRemoveEvent();
        $this->logManagerAction();
        $this->cleanup();

        return $this->success('', [$this->primaryKeyField => $this->object->get($this->primaryKeyField)]);
    }

    /**
     * Can contain post-removal logic.
     *
     * @return bool
     */
    public function afterRemove(): bool
    {
        if (!empty($this->customerId)) {
            $this->modx->removeCollection(msCustomerAddress::class, ['customer_id' => $this->customerId]);
        }

        return true;
    }
}
