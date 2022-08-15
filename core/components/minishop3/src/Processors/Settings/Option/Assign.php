<?php

namespace MiniShop3\Processors\Settings\Option;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MODX\Revolution\Processors\Model\CreateProcessor;

class Assign extends CreateProcessor
{
    /** @var msCategoryOption $object */
    public $object;
    public $classKey = msCategoryOption::class;
    public $objectType = 'ms_option';
    public $languageTopics = ['minishop3:default'];
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
     * @return bool|null|string
     */
    public function beforeSet()
    {
        $option_id = $this->getProperty('option_id');
        $category_id = $this->getProperty('category_id');

        if (!$option_id || !$this->modx->getCount(msOption::class, $option_id)) {
            return $this->modx->lexicon('msOption_err_ns');
        } elseif (!$category_id || !$this->modx->getCount(msCategory::class, $category_id)) {
            return $this->modx->lexicon('msCategoryOption_err_ns');
        }

        $key = [
            'option_id' => $option_id,
            'category_id' => $category_id,
            'active' => true,
        ];
        if (!$this->modx->getCount($this->classKey, $key)) {
            $key['position'] = $this->modx->getCount($this->classKey, ['category_id' => $category_id]);
            $this->object->fromArray($key, '', true, true);
        } else {
            return $this->modx->lexicon($this->objectType . '_err_ae', $key);
        }

        return true;
    }
}
