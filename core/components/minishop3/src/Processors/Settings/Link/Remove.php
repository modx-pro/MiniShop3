<?php

namespace MiniShop3\Processors\Settings\Link;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msLink;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msLink::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_save';


}
