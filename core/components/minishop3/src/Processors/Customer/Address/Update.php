<?php

namespace MiniShop3\Processors\Customer\Address;


use MiniShop3\Model\msCustomerAddress;
use MODX\Revolution\Processors\Model\UpdateProcessor;

class Update extends UpdateProcessor
{
    public $classKey = msCustomerAddress::class;
    public $objectType = 'msCustomer';
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msorder_save';

}
