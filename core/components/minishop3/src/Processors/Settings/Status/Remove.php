<?php

namespace MiniShop3\Processors\Settings\Status;

use MiniShop3\Model\msOrderStatus;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msOrderStatus::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_save';


    /**
     * @return bool|string
     */
    public function beforeRemove()
    {
        if (!$this->object->get('editable')) {
            return '';
        }

        return parent::beforeRemove();
    }
}
