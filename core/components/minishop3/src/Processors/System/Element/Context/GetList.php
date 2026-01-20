<?php

namespace MiniShop3\Processors\System\Element\Context;

use MODX\Revolution\modContext;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = modContext::class;
    public $languageTopics = ['context'];
    public $defaultSortField = 'key'; // Fallback, actual sort is set in prepareQueryBeforeCount


    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $c->select('key,name');

        // 'rank' is reserved word in MySQL 8.0+, escape with backticks
        $c->sortby('`rank`', 'ASC');
        $key = $this->getProperty('key');
        if (!empty($key)) {
            $c->where(['key' => $key]);
        }

        $exclude = $this->getProperty('exclude', 'mgr');
        if (!empty($exclude)) {
            $c->where([
                'key:NOT IN' => is_string($exclude) ? explode(',', $exclude) : $exclude,
            ]);
        }

        $query = trim($this->getProperty('query'));
        if (!empty($query)) {
            $c->where([
                'name:LIKE' => "%{$query}%"
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
                'key' => $object->get('key'),
                'name' => '(' . $object->get('key') . ') ' . $object->get('name'),
            ];
        } else {
            $array = $object->toArray();
        }

        return $array;
    }
}
