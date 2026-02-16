<?php

namespace MiniShop3\Processors\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msOrder;
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

}
