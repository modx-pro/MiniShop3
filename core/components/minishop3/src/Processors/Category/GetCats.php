<?php

namespace MiniShop3\Processors\Category;

use MiniShop3\Model\msCategory;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetCats extends GetListProcessor
{
    public $classKey = msCategory::class;
    public $objectType = 'msCategory';
    public $defaultSortField = 'id';
    public $defaultSortDirection = 'ASC';
    protected int $item_id = 0;

    /**
     * @return bool
     */
    public function initialize()
    {
        if ($this->getProperty('combo') && !$this->getProperty('limit') && $id = (int)$this->getProperty('id')) {
            $this->item_id = $id;
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
        $c->select('id,parent,pagetitle,context_key');
        $c->where([
            'class_key' => $this->classKey
        ]);

        if ($this->item_id) {
            $c->where(['id' => $this->item_id]);
        } elseif ($query = $this->getProperty('query')) {
            $c->where(['pagetitle:LIKE' => "%$query%"]);
        }

        return $c;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function iterate(array $data)
    {
        $list = [];
        $list = $this->beforeIteration($list);
        $this->currentIndex = 0;
        foreach ($data['results'] as $array) {
            // TODO: Этот метод iterate() отличается от базового отсутствием проверок доступов.
            // Проверить, правда ли это нужно.
            $objectArray = $this->prepareRow($array);
            if (!empty($objectArray) && is_array($objectArray)) {
                $list[] = $objectArray;
                $this->currentIndex++;
            }
        }
        return $this->afterIteration($list);
    }

    /**
     * Prepare the row for iteration
     *
     * @param xPDOObject $object
     *
     * @return array
     */
    public function prepareRow(xPDOObject $object)
    {
        $parents = $this->modx->getParentIds(
            $object->get('id'),
            2,
            ['context' => $object->get('context_key')]
        );
        if ($parents[count($parents) - 1] == 0) {
            unset($parents[count($parents) - 1]);
        }

        $resourceArray = $object->toArray();
        $resourceArray['parents'] = [];

        if (!empty($parents) && is_array($parents)) {
            $q = $this->modx->newQuery(msCategory::class, ['id:IN' => $parents]);
            $q->select('id,pagetitle');
            if ($q->prepare() && $q->stmt->execute()) {
                while ($row = $q->stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $key = array_search($row['id'], $parents);
                    if ($key !== false) {
                        $parents[$key] = $row;
                    }
                }
            }
            $resourceArray['parents'] = array_reverse($parents);
        }

        return $resourceArray;
    }
}