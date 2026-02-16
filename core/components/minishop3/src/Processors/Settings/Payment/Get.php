<?php

namespace MiniShop3\Processors\Settings\Payment;

use MiniShop3\Model\msPayment;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    /** @var msPayment $object */
    public $object;
    public $classKey = msPayment::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_view';


}
