<?php

namespace MiniShop3\Processors\Customer;

use MiniShop3\Model\msCustomer;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    use CustomerFieldAllowlistTrait;

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

        // Карточка покупателя не показывает хеш пароля и токен сессии, а отдавала их
        // каждому менеджеру с msorder_view (#908, тот же класс, что #840 и #848).
        return $this->success('', $this->allowedCustomerFields($array));
    }
}
