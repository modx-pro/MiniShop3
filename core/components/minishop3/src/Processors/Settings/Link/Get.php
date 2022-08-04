<?php

namespace MiniShop3\Processors\Settings\Link;

use MiniShop3\Model\msLink;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends  GetProcessor
{
    /** @var msLink $object */
    public $object;
    public $classKey = msLink::class;
    public $languageTopics = ['minishop'];
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
