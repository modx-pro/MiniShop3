<?php

namespace MiniShop3\Processors\Customer\Address;

use MiniShop3\Model\msCustomerAddress;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOQuery;
use xPDO\Om\xPDOQueryCondition;

class GetList extends GetListProcessor
{
    public $classKey = msCustomerAddress::class;
    public $languageTopics = ['default', 'minishop3:manager'];
    public $defaultSortField = 'id';
    public $defaultSortDirection = 'DESC';
    public $permission = 'msorder_list';
    /** @var  xPDOQuery $query */
    protected $query;

    /**
     * @return array
     */
    public function getData()
    {
        $c = $this->modx->newQuery($this->classKey);
        $c->where([
            'customer_id' => $this->getProperty('customer_id')
        ]);
        $c = $this->prepareQueryBeforeCount($c);
        $c = $this->prepareQueryAfterCount($c);

        $c->prepare();
        return [
            'results' => ($c->stmt->execute()) ? $c->stmt->fetchAll(\PDO::FETCH_ASSOC) : [],
            'total' => (int)$this->getProperty('total'),
        ];
    }

    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $this->query = clone $c;
        $c->select(
            $this->modx->getSelectColumns(msCustomerAddress::class, 'msCustomerAddress')
        );
        return $c;
    }

    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryAfterCount(xPDOQuery $c)
    {
        $total = 0;
        $limit = (int)$this->getProperty('limit');
        $start = (int)$this->getProperty('start');

        $q = clone $c;
        $q->query['columns'] = ['SQL_CALC_FOUND_ROWS msCustomerAddress.id'];
        $sortClassKey = $this->getSortClassKey();
        $sortKey = $this->modx->getSelectColumns(
            $sortClassKey,
            $this->getProperty('sortAlias', 'msCustomerAddress'),
            '',
            [$this->getProperty('sort')]
        );
        if (empty($sortKey)) {
            $sortKey = $this->getProperty('sort');
        }

        $q->sortby($sortKey, $this->getProperty('dir'));
        if ($limit > 0) {
            $q->limit($limit, $start);
        }

        $ids = [];
        if ($q->prepare() && $q->stmt->execute()) {
            $ids = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
            $total = $this->modx->query('SELECT FOUND_ROWS()')->fetchColumn();
        }
        $ids = empty($ids) ? "(0)" : "(" . implode(',', $ids) . ")";
        $c->query['where'] = [
            [
                new xPDOQueryCondition(['sql' => 'msCustomerAddress.id IN ' . $ids, 'conjunction' => 'AND']),
            ]
        ];
        $c->sortby($sortKey, $this->getProperty('dir'));

        $this->setProperty('total', $total);

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
            $list[] = $this->prepareArray($array);
            $this->currentIndex++;
        }
        return $this->afterIteration($list);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function prepareArray(array $data)
    {
        $data['actions'] = [
            [
                'cls' => '',
                'icon' => 'icon icon-edit',
                'title' => $this->modx->lexicon('ms3_menu_update'),
                'action' => 'updateAddress',
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
                'action' => 'removeAddress',
                'button' => true,
                'menu' => true,
            ],
        ];

        return $data;
    }

    /**
     * @param array $array
     * @param bool $count
     *
     * @return string
     */
    public function outputArray(array $array, $count = false)
    {
        if ($count === false) {
            $count = count($array);
        }

        $data = [
            'success' => true,
            'results' => $array,
            'total' => $count,
        ];

        return json_encode($data);
    }
}
