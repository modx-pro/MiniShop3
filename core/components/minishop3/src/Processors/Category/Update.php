<?php

namespace MiniShop3\Processors\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Processors\Resource\EnsureTargetClassKeyTrait;
use MODX\Revolution\Processors\Resource\Update as UpdateProcessor;

class Update extends UpdateProcessor
{
    use EnsureTargetClassKeyTrait;

    public $classKey = msCategory::class;
    public $languageTopics = ['resource', 'minishop3:default'];
    public $permission = 'mscategory_save';
    public $beforeSaveEvent = 'OnBeforeDocFormSave';
    public $afterSaveEvent = 'OnDocFormSave';

    /**
     * @return int|mixed|string
     */
    public function checkFriendlyAlias()
    {
        $id_as_alias = $this->workingContext->getOption('ms3_category_id_as_alias');
        if ($id_as_alias) {
            $alias = $this->object->get('id');
            $this->setProperty('alias', $alias);
        } else {
            $alias = parent::checkFriendlyAlias();
        }

        return $alias;
    }


    /**
     * @return void
     */
    public function handleCheckBoxes()
    {
        parent::handleCheckBoxes();
        $this->setCheckbox('hide_children_in_tree');
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
