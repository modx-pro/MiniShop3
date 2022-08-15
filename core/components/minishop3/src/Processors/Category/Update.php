<?php

namespace MiniShop3\Processors\Category;

use MODX\Revolution\Processors\Resource\Update as UpdateProcessor;
use MiniShop3\Model\msCategory;
use MODX\Revolution\modResource;

class Update extends UpdateProcessor
{
    public $classKey = msCategory::class;
    public $languageTopics = ['resource', 'minishop3:default'];
    public $permission = 'mscategory_save';
    public $beforeSaveEvent = 'OnBeforeDocFormSave';
    public $afterSaveEvent = 'OnDocFormSave';

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        $primaryKey = $this->getProperty($this->primaryKeyField, false);
        if (empty($primaryKey)) {
            return $this->modx->lexicon($this->classKey . '_err_ns');
        }

        if (!$this->modx->getCount($this->classKey, ['id' => $primaryKey, 'class_key' => $this->classKey])) {
            $res = $this->modx->getObject(modResource::class, ['id' => $primaryKey]);
            if ($res) {
                $res->set('class_key', $this->classKey);
                $res->save();
            }
        }

        return parent::initialize();
    }


    /**
     * @return int|mixed|string
     */
    public function checkFriendlyAlias()
    {
        $id_as_alias = $this->workingContext->getOption('ms_category_id_as_alias');
        if ($id_as_alias) {
            $alias = $this->object->get('id');
            $this->setProperty('alias', $alias);
        } else {
            $alias = parent::checkFriendlyAlias();
        }

        return $alias;
    }


    /**
     * @return bool
     */
    public function beforeSave()
    {
        $this->object->set('isfolder', true);
        return parent::beforeSave();
    }
}
