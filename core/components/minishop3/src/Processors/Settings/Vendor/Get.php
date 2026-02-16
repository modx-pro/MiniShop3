<?php

namespace MiniShop3\Processors\Settings\Vendor;

use MiniShop3\Model\msVendor;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    public $classKey = msVendor::class;
    public $objectType = 'msVendor';
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_view';
}
