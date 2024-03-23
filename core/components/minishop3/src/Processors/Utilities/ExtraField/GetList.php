<?php

namespace MiniShop3\Processors\Utilities\ExtraField;

use MiniShop3\Model\msExtraField;
use MiniShop3\Utils\DBManager;
use MODX\Revolution\Processors\Model\GetListProcessor;
use PDO;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = msExtraField::class;
    public $defaultSortField = 'id';
    public $defaultSortDirection = 'asc';
    public $permission = 'mssetting_list';

    private DBManager $dbManager;

    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function initialize()
    {
        $this->dbManager = new DBManager($this->modx);

        return parent::initialize();
    }

    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        if ($this->getProperty('combo')) {
            $c->select('id,key');
        }
        if ($id = (int)$this->getProperty('id')) {
            $c->where(['id' => $id]);
        }

        if ($class = trim($this->getProperty('class'))) {
            $c->where(['class' => $class]);
        }

        if ($query = trim($this->getProperty('query'))) {
            $c->where([
                'key:LIKE' => "%{$query}%",
                'OR:label:LIKE' => "%{$query}%",
            ]);
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
        if ($this->getProperty('combo')) {
            $data = [
                'id' => $object->get('id'),
                'key' => $object->get('key'),
            ];
        } else {
            $data = $object->toArray();
            $data['exists'] = $this->dbManager->hasField($data['class'], $data['key']);
            $data['dbtype'] = empty($data['precision'])
                ? $data['dbtype']
                : sprintf("%s (%s)", $data['dbtype'], $data['precision']);

            $data['actions'] = [];

            $data['actions'][] = [
                'cls' => '',
                'icon' => 'icon icon-edit',
                'title' => $this->modx->lexicon('ms3_menu_update'),
                'action' => 'updateExtraField',
                'button' => true,
                'menu' => true,
            ];

            $data['actions'][] = [
                'cls' => [
                    'menu' => 'red',
                    'button' => 'red',
                ],
                'icon' => 'icon icon-trash-o',
                'title' => $this->modx->lexicon('ms3_menu_remove'),
                'multiple' => $this->modx->lexicon('ms3_menu_remove_multiple'),
                'action' => 'removeExtraField',
                'button' => true,
                'menu' => true,
            ];
        }

        return $data;
    }
}
