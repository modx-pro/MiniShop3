<?php

namespace MiniShop3\Processors\Utilities\ExtraField;

use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msVendor;
use MiniShop3\Utils\ExtraFields;
use MODX\Revolution\Processors\Model\UpdateProcessor;
use xPDO\xPDO;

class Update extends UpdateProcessor
{
    /** @var msExtraField $object */
    public $object;
    public $classKey = msExtraField::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_save';

    private $createColumn = false;

    /** @var ExtraFields $extraFields */
    private $extraFields;

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }

        $this->extraFields = new ExtraFields($this->modx);

        return parent::initialize();
    }

    /**
     * @return bool
     */
    public function beforeSet()
    {
        $existsInDb = $this->extraFields->columnExists($this->object->get('class'), $this->object->get('key'));
        if ($existsInDb) {
            $this->createColumn = false;
            $required = [];
            // We cannot change these fields if the field exists in the database
            $this->unsetProperty('class');
            $this->unsetProperty('key');
            $this->unsetProperty('dbtype');
            $this->unsetProperty('precision');
            $this->unsetProperty('null');
            $this->unsetProperty('default');
            $this->unsetProperty('default_value');
            $this->unsetProperty('attributes');
        } else {
            $this->createColumn = filter_var($this->getProperty('create'), FILTER_VALIDATE_BOOLEAN);
            $required = ['class', 'key'];
            if ($this->createColumn) {
                $required = array_merge($required, ['dbtype', 'phptype']);
            }
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

        if (!$existsInDb) {
            $class = $this->getProperty('class');
            $key = $this->getProperty('key');
            $doesAlreasyExistCriteria = [
                'id:!=' => $this->object->get('id'),
                'class' => $class,
                'key' => $key
            ];
            if ($this->doesAlreadyExist($doesAlreasyExistCriteria) || $this->doesBuiltIn($class, $key)) {
                $this->modx->error->addField('key', $this->modx->lexicon('ms3_err_ae'));
            }
        }

        return !$this->hasErrors();
    }

    /**
     * @return array
     */
    public function beforeSave()
    {
        if ($this->createColumn) {
            $class = $this->object->get('class');
            $key = $this->object->get('key');
            // TODO: Не однозначное поведение, если в базе существует столбец, а пользователь создаст с другим dbtype
            if (!$this->extraFields->columnExists($class, $key)) {
                if (!$this->extraFields->createColumn($this->object)) {
                    // TODO: заменить текст ошибки на "Ошибка добавления поля"
                    $this->modx->error->addField('key', $this->modx->lexicon('ms3_err_unknown'));
                }
            }
        }

        return parent::beforeSave();
    }

    public function afterSave()
    {
        $this->extraFields->deleteCache();
        return parent::afterSave();
    }


    /**
     * Checks if the specified field is built-in.
     *
     * @param string $key
     * @return boolean
     */
    private function doesBuiltIn(string $className, string $fieldName): bool
    {
        $builtInFields = [
            msProductData::class => [
                'id', 'article', 'price', 'old_price', 'weight', 'image', 'thumb', 'vendor_id',
                'made_in', 'new', 'popular', 'favorite', 'tags', 'color', 'size', 'source_id'
            ],
            msVendor::class => [
                'id', 'position', 'name', 'resource_id', 'country', 'logo', 'address', 'phone', 'email',
                'description', 'properties'
            ]
        ];
        if (!array_key_exists($className, $builtInFields)) {
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'The specified class is not supported: ' . $className);
            return false;
        }
        $normalizedFieldName = strtolower(trim($fieldName));
        return in_array($normalizedFieldName, $builtInFields[$className]);
    }
}
