<?php

namespace MiniShop3\Processors\Settings\Option;

use MiniShop3\Model\msOption;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msOption::class;
    public $objectType = 'ms_option';
    public $languageTopics = ['minishop:default'];
    public $permission = 'mssetting_save';


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
