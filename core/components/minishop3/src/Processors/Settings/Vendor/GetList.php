<?php

namespace MiniShop3\Processors\Settings\Vendor;

use MiniShop3\Model\msVendor;
use MODX\Revolution\modResource;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = msVendor::class;
    public $objectType = 'msVendor';
    public $defaultSortField = 'id';
    public $defaultSortDirection = 'asc';
    public $permission = 'mssetting_list';
    protected $item_id = 0;


    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if ($this->getProperty('combo') && !$this->getProperty('limit') && $id = (int)$this->getProperty('id')) {
            $this->item_id = $id;
        }
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }

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
            $c->select('id,name');
        } else {
            $c->leftJoin(modResource::class, 'Resource');
            $c->select($this->modx->getSelectColumns($this->classKey, 'msVendor'));
            $c->select('Resource.pagetitle');
        }

        if (!empty($this->item_id)) {
            $c->where(['id' => $this->item_id]);
            return $c;
        }

        $query = trim($this->getProperty('query'));
        if (!empty($query)) {
            $c->where([
                'name:LIKE' => "%{$query}%",
                'OR:description:LIKE' => "%{$query}%",
                'OR:country:LIKE' => "%{$query}%",
                'OR:email:LIKE' => "%{$query}%",
                'OR:address:LIKE' => "%{$query}%",
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
                'name' => $object->get('name'),
            ];
        } else {
            $data = $object->toArray();
            if (!$data['resource_id']) {
                $data['resource_id'] = null;
            }
            $data['actions'] = [];

            $data['actions'][] = [
                'cls' => '',
                'icon' => 'icon icon-edit',
                'title' => $this->modx->lexicon('ms3_menu_update'),
                'action' => 'updateVendor',
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
                'action' => 'removeVendor',
                'button' => true,
                'menu' => true,
            ];
        }

        return $data;
    }
}
