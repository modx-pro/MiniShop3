<?php

namespace MiniShop3\Processors\Category\Option;

use MODX\Revolution\Processors\Model\RemoveProcessor;
use MiniShop3\Model\msCategoryOption;

class Delete extends RemoveProcessor
{
    public $classKey = msCategoryOption::class;
    public $objectType = 'ms_option';
    public $languageTopics = ['minishop:default'];
    public $permission = 'mscategory_save';

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        $this->object = $this->modx->getObject($this->classKey, [
            'option_id' => $this->getProperty('option_id'),
            'category_id' => $this->getProperty('category_id'),
        ]);
        if (empty($this->object)) {
            return $this->modx->lexicon('ms_option_err_nfs');
        }

        return true;
    }

    /**
     * @return bool
     */
    public function afterRemove()
    {
        $sql = "UPDATE {$this->modx->getTableName($this->classKey)} SET `position`=`position`-1
            WHERE `position`>{$this->object->get('position')} AND `category_id`={$this->object->get('category_id')}";
        $this->modx->exec($sql);

        return parent::afterRemove();
    }
}
