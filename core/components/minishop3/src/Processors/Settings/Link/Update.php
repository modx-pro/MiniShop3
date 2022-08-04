<?php

namespace MiniShop3\Processors\Settings\Link;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msLink;
use MODX\Revolution\Processors\Model\UpdateProcessor;

class Update extends UpdateProcessor
{
    /** @var msLink $object */
    public $object;
    public $classKey = msLink::class;
    public $languageTopics = ['minishop'];
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

    /**
     * @return bool
     */
    public function beforeSet()
    {
        $required = ['name'];
        foreach ($required as $field) {
            if (!$tmp = trim($this->getProperty($field))) {
                $this->addFieldError($field, $this->modx->lexicon('field_required'));
            } else {
                $this->setProperty($field, $tmp);
            }
        }
        $name = $this->getProperty('name');
        if ($this->modx->getCount($this->classKey, ['name' => $name, 'id:!=' => $this->object->get('id')])) {
            $this->modx->error->addField('name', $this->modx->lexicon('ms_err_ae'));
        }
        $this->unsetProperty('type');

        return !$this->hasErrors();
    }
}
