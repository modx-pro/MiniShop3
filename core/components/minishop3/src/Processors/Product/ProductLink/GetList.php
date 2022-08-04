<?php

namespace MiniShop3\Processors\Product\ProductLink;

use MiniShop3\Model\msLink;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductLink;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = msProductLink::class;
    public $defaultSortField = 'link';
    public $defaultSortDirection = 'ASC';
    public $permission = '';

    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        if ($master = $this->getProperty('master')) {
            $c->orCondition(['master' => $master, 'slave' => $master]);
        }
        $c->innerJoin(msLink::class, 'msLink', 'msProductLink.link=msLink.id');
        $c->leftJoin(msProduct::class, 'Master', 'Master.id=msProductLink.master');
        $c->leftJoin(msProduct::class, 'Slave', 'Slave.id=msProductLink.slave');
        $c->select($this->modx->getSelectColumns(msProductLink::class, 'msProductLink'));
        $c->select($this->modx->getSelectColumns(msLink::class, 'msLink', '', ['id'], true));
        $c->select('Master.pagetitle as master_pagetitle, Slave.pagetitle as slave_pagetitle');

        $query = trim($this->getProperty('query'));
        if (!empty($query)) {
            $c->where([
                'msLink.name:LIKE' => "%{$query}%",
                'OR:Master.pagetitle:LIKE' => "%{$query}%",
                'OR:Slave.pagetitle:LIKE' => "%{$query}%",
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
        $data = $object->toArray();

        $data['actions'] = [
            [
                'cls' => [
                    'menu' => 'red',
                    'button' => 'red',
                ],
                'icon' => 'icon icon-trash-o',
                'title' => $this->modx->lexicon('ms_menu_remove'),
                'multiple' => $this->modx->lexicon('ms_menu_remove_multiple'),
                'action' => 'removeLink',
                'button' => true,
                'menu' => true,
            ],
        ];

        return $data;
    }
}
