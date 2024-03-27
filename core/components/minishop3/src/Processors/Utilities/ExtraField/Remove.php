<?php

namespace MiniShop3\Processors\Utilities\ExtraField;

use MiniShop3\Model\msExtraField;
use MiniShop3\Utils\ExtraFields;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $classKey = msExtraField::class;
    public $languageTopics = ['minishop3'];
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

    public function afterRemove()
    {
        $extraFields = new ExtraFields($this->modx);
        $extraFields->deleteCache();

        return parent::afterRemove();
    }
}
