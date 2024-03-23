<?php

namespace MiniShop3\Processors\Utilities\ExtraField;

use MiniShop3\Model\msExtraField;
use MiniShop3\Utils\DBManager;
use MODX\Revolution\Processors\Model\CreateProcessor;

class Create extends CreateProcessor
{
    /** @var msExtraField $object */
    public $object;
    public $classKey = msExtraField::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_save';

    private $createColumn = false;

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
        $this->createColumn = filter_var($this->getProperty('create'), FILTER_VALIDATE_BOOLEAN);

        $required = ['class', 'key'];
        if ($this->createColumn) {
            $required = array_merge($required, ['dbtype', 'phptype']);
        }
        foreach ($required as $field) {
            if (!$tmp = trim($this->getProperty($field))) {
                $this->addFieldError($field, $this->modx->lexicon('field_required'));
            } else {
                $this->setProperty($field, $tmp);
            }
        }

        if ($this->hasErrors()) {
            return false;
        }

        $className = $this->getProperty('class');
        $key = $this->getProperty('key');
        $classFields = $this->modx->getFields($className);
        if (!empty($classFields)) {
            if (array_key_exists($key, $classFields)) {
                $this->addFieldError('key', $this->modx->lexicon('ms3_err_ae'));
            }
        }

        $doesAlreasyExistCriteria = [
            'class' => $this->getProperty('class'),
            'key' => $this->getProperty('key')
        ];
        if ($this->doesAlreadyExist($doesAlreasyExistCriteria)) {
            $this->modx->error->addField('key', $this->modx->lexicon('ms3_err_ae'));
        }

        return !$this->hasErrors();
    }

    /**
     * @return array
     */
    public function beforeSave()
    {
        if ($this->createColumn) {
            $dbManager = new DBManager($this->modx);
            $class = $this->object->get('class');
            $key = $this->object->get('key');
            // TODO: Не однозначное поведение, если в базе существует столбец, а пользователь создаст с другим dbtype
            if (!$dbManager->hasField($class, $key)) {
                if (!$dbManager->addField($this->object)) {
                    // TODO: заменить текст ошибки на "Ошибка добавления поля"
                    $this->modx->error->addField('key', $this->modx->lexicon('ms3_err_unknown'));
                }
            }
        }

        return parent::beforeSave();
    }
}
