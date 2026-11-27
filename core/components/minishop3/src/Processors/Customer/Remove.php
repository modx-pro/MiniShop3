<?php

namespace MiniShop3\Processors\Customer;

use MiniShop3\Model\msCustomer;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msCustomer::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msorder_remove';

    public function process()
    {
        $canRemove = $this->beforeRemove();
        if ($canRemove !== true) {
            return $this->failure(is_string($canRemove) ? $canRemove : '');
        }
        $preventRemoval = $this->fireBeforeRemoveEvent();
        if (!empty($preventRemoval)) {
            return $this->failure(is_string($preventRemoval) ? $preventRemoval : '');
        }

        if ($this->removeObject() === false) {
            return $this->failure($this->modx->lexicon($this->objectType . '_err_remove'));
        }
        $this->fireAfterRemoveEvent();
        $this->logManagerAction();
        $this->cleanup();

        return $this->success('', [$this->primaryKeyField => $this->object->get($this->primaryKeyField)]);
    }
}
