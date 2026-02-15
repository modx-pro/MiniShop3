<?php

namespace MiniShop3\Processors\Settings\Delivery;

use MiniShop3\Model\msDelivery;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msDelivery::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_save';


}
