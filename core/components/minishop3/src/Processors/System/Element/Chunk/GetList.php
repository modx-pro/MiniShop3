<?php

namespace MiniShop3\Processors\System\Element\Chunk;

use MODX\Revolution\modChunk;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = modChunk::class;
    public $languageTopics = ['chunk'];
    public $defaultSortField = 'name';


    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $categories = $this->modx->getOption('ms_chunks_categories');
        if (!empty($categories)) {
            $c->where([
                'category:IN' => explode(',', $categories)
            ]);
        }
        if ($id = (int)$this->getProperty('id')) {
            $c->where([
                'id' => $id
            ]);
        }
        $query = trim($this->getProperty('query'));
        if (!empty($query)) {
            $c->where([
                'name:LIKE' => "%{$query}%",
                'OR:description:LIKE' => "%{$query}%",
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
        $array = $object->toArray();

        if (!empty($array['description'])) {
            $array['name'] = $array['description'];
        }

        return $array;
    }
}
