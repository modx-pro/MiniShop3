<?php

namespace MiniShop3\Processors\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Customer\CustomerManagerFields;
use MODX\Revolution\Processors\Model\UpdateProcessor;
use MODX\Revolution\Validation\modValidator;

class Update extends UpdateProcessor
{
    public $classKey = msCustomer::class;
    public $objectType = 'msCustomer';
    public $languageTopics = ['minishop3:default'];
    public $beforeSaveEvent = 'msOnBeforeUpdateCustomer';
    public $afterSaveEvent = 'msOnUpdateCustomer';
    public $permission = 'msorder_save';

    /**
     * Ядро отдаёт в ответе сам объект (UpdateProcessor::cleanup() → success('', $this->object)),
     * а modError::process() разворачивает его полным toArray() — вместе с хешем пароля и
     * токеном сессии. Пустой апдейт становился способом прочитать их (#908).
     *
     * @return array|string
     */
    public function cleanup()
    {
        return $this->success('', CustomerManagerFields::pick($this->object->toArray()));
    }
}
