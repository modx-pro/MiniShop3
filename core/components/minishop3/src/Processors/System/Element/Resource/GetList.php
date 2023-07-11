<?php

namespace MiniShop3\Processors\System\Element\Resource;

use MODX\Revolution\modResource;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = modResource::class;
    public $languageTopics = ['resource'];
    public $defaultSortField = 'pagetitle';


    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        if ($this->getProperty('combo')) {
            $c->select('id,pagetitle');
        }
        if ($id = (int)$this->getProperty('id')) {
            $c->where([
                'id' => $id
            ]);
        }
        $query = trim($this->getProperty('query'));
        if (!empty($query)) {
            $c->where([
                'pagetitle:LIKE' => "%{$query}%"
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
            $array = [
                'id' => $object->get('id'),
                'pagetitle' => '(' . $object->get('id') . ') ' . $object->get('pagetitle'),
            ];
        } else {
            $array = $object->toArray();
        }

        return $array;
    }
}
