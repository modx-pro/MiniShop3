<?php

namespace MiniShop3\Processors\Settings\Payment;

use MiniShop3\Model\msPayment;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msPayment::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_save';


}
