<?php

namespace MiniShop3\Processors\Settings\Status;

use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPayment;
use MODX\Revolution\Processors\Model\UpdateProcessor;

class Update extends UpdateProcessor
{
    /** @var msPayment $object */
    public $object;
    public $classKey = msOrderStatus::class;
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

    /**
     * @return bool
     */
    public function beforeSet()
    {
        $required = ['name'];
        if ($this->getProperty('email_user')) {
            $required[] = 'subject_user';
            $required[] = 'body_user';
        }
        if ($this->getProperty('email_manager')) {
            $required[] = 'subject_manager';
            $required[] = 'body_manager';
        }
        foreach ($required as $field) {
            if (!$tmp = trim($this->getProperty($field))) {
                $this->addFieldError($field, $this->modx->lexicon('field_required'));
            } else {
                $this->setProperty($field, $tmp);
            }
        }
        $name = $this->getProperty('name');
        if ($this->modx->getCount($this->classKey, ['name' => $name, 'id:!=' => $this->object->get('id')])) {
            $this->modx->error->addField('name', $this->modx->lexicon('ms3_err_ae'));
        }

        return !$this->hasErrors();
    }
}
