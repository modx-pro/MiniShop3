<?php

namespace MiniShop3\Processors\Category\Option;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MODX\Revolution\Processors\Model\CreateProcessor;

class Add extends CreateProcessor
{
    public $classKey = msCategoryOption::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'mscategory_save';
    /** @var  msCategoryOption */
    public $object;


    /**
     * @return bool|null|string
     */
    public function beforeSet()
    {
        $option = (int)$this->getProperty('option_id');
        $category = (int)$this->getProperty('category_id');
        if (!$option) {
            return $this->modx->lexicon('ms3_option_err_ns');
        } elseif (!$category) {
            return $this->modx->lexicon('ms3_category_err_ns');
        }

        $unique = [
            'option_id' => $option,
            'category_id' => $category,
        ];

        if ($this->doesAlreadyExist($unique)) {
            return $this->modx->lexicon('ms3_option_err_ae', $unique);
        }

        if (!$this->modx->getCount(msOption::class, $option)) {
            return $this->modx->lexicon('ms3_option_err_nf');
        } elseif (!$this->modx->getCount(msCategory::class, $category)) {
            return $this->modx->lexicon('ms3_category_err_nf');
        }

        $this->object->set('option_id', $option);
        $this->object->set('category_id', $category);

        $rank = $this->modx->getCount($this->classKey, ['category_id' => $category]);
        $this->object->set('position', $rank);

        return parent::beforeSet();
    }
}
