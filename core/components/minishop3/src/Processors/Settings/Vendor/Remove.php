<?php

namespace MiniShop3\Processors\Settings\Vendor;

use MiniShop3\Model\msVendor;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msVendor::class;
    public $objectType = 'msVendor';
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_save';
    public $beforeRemoveEvent = 'msOnBeforeVendorDelete';
    public $afterRemoveEvent = 'msOnVendorDelete';

}
