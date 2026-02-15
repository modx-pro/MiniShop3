<?php

namespace MiniShop3\Processors\Customer\Address;

use MiniShop3\Model\msCustomerAddress;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    public $classKey = msCustomerAddress::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msorder_view';


    /**
     * @return array|string
     */
    public function cleanup()
    {
        $array = $this->object->toArray();
        return $this->success('', $array);
    }
}
