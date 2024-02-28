<?php

namespace MiniShop3\Processors\Settings\Option;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    /** @var msOption $object */
    public $object;
    public $classKey = msOption::class;
    public $objectType = 'ms3_option';
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_view';


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
     * @return void
     */
    public function beforeOutput()
    {
        $c = $this->modx->newQuery(msCategory::class);
        $c->leftJoin(msCategoryOption::class, 'msCategoryOption', 'msCategoryOption.category_id = msCategory.id');
        $c->where(['msCategoryOption.option_id' => $this->object->get('id')]);
        $c->select([
            $this->modx->getSelectColumns(msCategory::class, 'msCategory'),
            $this->modx->getSelectColumns(
                msCategoryOption::class,
                'msCategoryOption',
                '',
                ['id', 'option_id', 'category_id'],
                true
            ),
        ]);
        $categories = $this->modx->getIterator(msCategory::class, $c);

        $data = [];
        /** @var msCategory $category */
        foreach ($categories as $category) {
            $data[] = $category->toArray();
        }
        $this->object->set('categories', $data);

        $data = [];
        $categories = $this->object->getIterator('OptionCategories');
        /** @var msCategoryOption $cat */
        foreach ($categories as $cat) {
            if ($category = $cat->getOne('Category')) {
                $data[$category->get('id')] = 1;
            }
        }
        $this->object->set('categories', json_encode($data));
        $this->object->set('properties', $this->object->getInputProperties());
    }
}
