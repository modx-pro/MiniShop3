<?php

namespace MiniShop3\Processors\Settings\Option;

use MiniShop3\Model\msOption;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msOption::class;
    public $objectType = 'ms3_option';
    public $languageTopics = ['minishop3:default'];
    public $permission = 'mssetting_save';


}
