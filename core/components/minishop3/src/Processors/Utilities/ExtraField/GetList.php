<?php

namespace MiniShop3\Processors\Utilities\ExtraField;

use MiniShop3\Model\msExtraField;
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
            $data['exists'] = $this->existsInDatabase($data['class'], $data['key']);
            $data['dbtype'] = sprintf("%s (%s)", $data['dbtype'], $data['precision']);

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

    private function existsInDatabase($class, $column): bool
    {
        if (!empty($class)) {
            $c = $this->modx->prepare("SHOW COLUMNS IN {$this->modx->getTableName($class)}");
            $c->execute();
            while ($cl = $c->fetch(PDO::FETCH_ASSOC)) {
                if($column === $cl['Field']) {
                    return true;
                }
            }
        }

        return false;
    }
}
