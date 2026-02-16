<?php

namespace MiniShop3\Processors\Settings\Link;

use MiniShop3\Model\msLink;
use MODX\Revolution\Processors\Model\CreateProcessor;

class Create extends CreateProcessor
{
    /** @var msLink $object */
    public $object;
    public $classKey = msLink::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_save';


    /**
     * @return bool
     */
    public function beforeSet()
    {
        $required = ['name', 'type'];
        foreach ($required as $field) {
            if (!$tmp = trim($this->getProperty($field))) {
                $this->addFieldError($field, $this->modx->lexicon('field_required'));
            } else {
                $this->setProperty($field, $tmp);
            }
        }
        if ($this->modx->getCount($this->classKey, ['name' => $this->getProperty('name')])) {
            $this->modx->error->addField('name', $this->modx->lexicon('ms3_err_ae'));
        }

        return !$this->hasErrors();
    }
}
