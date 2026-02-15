<?php

namespace MiniShop3\Processors\Customer;

use MiniShop3\Model\msCustomer;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    public $classKey = msCustomer::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msorder_view';


    /**
     * @return array|string
     */
    public function cleanup()
    {
        $ms3 = $this->modx->services->get('ms3');
        $array = $this->object->toArray();
        return $this->success('', $array);
    }
}
