<?php

namespace MiniShop3\Processors\Settings\Delivery;

use MiniShop3\Model\msDelivery;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    /** @var msDelivery $object */
    public $object;
    public $classKey = msDelivery::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_view';


}
