<?php

namespace MiniShop3\Processors\Settings\Option;

use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MODX\Revolution\modCategory;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = msOption::class;
    public $defaultSortField = 'key';
    public $defaultSortDirection = 'asc';
    public $objectType = 'ms3';
    public $permission = 'mssetting_list';
    public $languageTopics = ['minishop3:default'];


    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {

        $query = $this->getProperty('query', '');
        if (!empty($query)) {
            $c->where([
                'msOption.key:LIKE' => "%{$query}%",
                'OR:msOption.caption:LIKE' => "%{$query}%",
            ]);
        }

        $category = (int)$this->getProperty('category_id', 0);
        $categories = $this->getProperty('categories', '[]');
        $categories = json_decode($categories, true);

        if (($category > 0) || (count($categories) > 0)) {
            $c->leftJoin(msCategoryOption::class, 'msCategoryOption', 'msCategoryOption.option_id=msOption.id');
            $c->select(array(
                $this->modx->getSelectColumns(msOption::class, 'msOption'),
                $this->modx->getSelectColumns(
                    msCategoryOption::class,
                    'msCategoryOption',
                    '',
                    array('id', 'option_id'),
                    true
                ),
            ));
        }

        if ($category > 0) {
            $c->where([
                'msCategoryOption.category_id' => $category,
            ]);
        }

        if (count($categories) > 0) {
            $c->where([
                'msCategoryOption.category_id:IN' => $categories,
            ]);
        }

        $mod_category = $this->getProperty('modcategory', '');

        if (is_numeric($mod_category)) {
            if ($mod_category > 0) {
                $c->leftJoin(modCategory::class, 'modCategory', 'modCategory.id=msOption.category_id');
                $c->where([
                    'modCategory.id' => $mod_category,
                ]);
            } else {
                $c->where([
                    'msOption.category_id' => $mod_category,
                ]);
            }
        }

        return $c;
    }


    /**
     * @param xPDOObject $object
     *
     * @return array
     */
    public function prepareRow(xPDOObject $object)
    {
        $data = $object->toArray();

        $data['actions'] = [
            [
                'cls' => '',
                'icon' => 'icon icon-edit',
                'title' => $this->modx->lexicon('ms3_menu_update'),
                'action' => 'updateOption',
                'button' => true,
                'menu' => true,
            ],
            [
                'cls' => [
                    'menu' => 'red',
                    'button' => 'red',
                ],
                'icon' => 'icon icon-trash-o',
                'title' => $this->modx->lexicon('ms3_menu_remove'),
                'multiple' => $this->modx->lexicon('ms3_menu_remove_multiple'),
                'action' => 'removeOption',
                'button' => true,
                'menu' => true,
            ],
        ];

        return $data;
    }
}
