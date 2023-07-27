<?php

namespace MiniShop3\Processors\Settings\Status;

use MiniShop3\Model\msOrderStatus;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    public $object;
    public $classKey = msOrderStatus::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_view';


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
