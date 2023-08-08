<?php

namespace MiniShop3\Processors\Settings\Vendor;

use MiniShop3\Model\msVendor;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msVendor::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_save';
    public $beforeRemoveEvent = 'msOnBeforeVendorDelete';
    public $afterRemoveEvent = 'msOnAfterVendorDelete';


    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }

        return parent::initialize();
    }
}
