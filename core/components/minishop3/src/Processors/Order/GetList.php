<?php

namespace MiniShop3\Processors\Order;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOQuery;
use xPDO\Om\xPDOQueryCondition;

class GetList extends GetListProcessor
{
    public $classKey = msOrder::class;
    public $languageTopics = ['default', 'minishop:manager'];
    public $defaultSortField = 'id';
    public $defaultSortDirection = 'DESC';
    public $permission = 'msorder_list';
    /** @var  xPDOQuery $query */
    protected $query;

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }

        return parent::initialize();
    }

    /**
     * @return array
     */
    public function getData()
    {
        $c = $this->modx->newQuery($this->classKey);
        $c = $this->prepareQueryBeforeCount($c);
        $c = $this->prepareQueryAfterCount($c);
        return [
            'results' => ($c->prepare() && $c->stmt->execute()) ? $c->stmt->fetchAll(\PDO::FETCH_ASSOC) : [],
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
        $c->leftJoin(modUser::class, 'User');
        $c->leftJoin(modUserProfile::class, 'UserProfile');
        $c->leftJoin(msOrderStatus::class, 'Status');
        $c->leftJoin(msDelivery::class, 'Delivery');
        $c->leftJoin(msPayment::class, 'Payment');

        $query = trim($this->getProperty('query'));
        if (!empty($query)) {
            if (is_numeric($query)) {
                $c->andCondition([
                    'id' => $query,
                ]);
            } else {
                $c->where([
                    'num:LIKE' => "{$query}%",
                    'OR:comment:LIKE' => "%{$query}%",
                    'OR:User.username:LIKE' => "%{$query}%",
                    'OR:UserProfile.fullname:LIKE' => "%{$query}%",
                    'OR:UserProfile.email:LIKE' => "%{$query}%",
                ]);
            }
        }
        if ($status = $this->getProperty('status_id')) {
            $c->where([
                'status_id' => $status,
            ]);
        }
        if ($customer = $this->getProperty('customer')) {
            $c->where([
                'user_id' => (int)$customer,
            ]);
        }
        if ($context = $this->getProperty('context')) {
            $c->where([
                'context' => $context,
            ]);
        }
        if ($date_start = $this->getProperty('date_start')) {
            $c->andCondition([
                'createdon:>=' => date('Y-m-d 00:00:00', strtotime($date_start)),
            ], null, 1);
        }
        if ($date_end = $this->getProperty('date_end')) {
            $c->andCondition([
                'createdon:<=' => date('Y-m-d 23:59:59', strtotime($date_end)),
            ], null, 1);
        }

        $this->query = clone $c;

        $exclude = ['status_id', 'delivery_id', 'payment_id'];
        $c->select(
            $this->modx->getSelectColumns(msOrder::class, 'msOrder', '', $exclude, true) . ',
            msOrder.status_id, msOrder.delivery_id, msOrder.payment_id,
            UserProfile.fullname as customer, User.username as customer_username,
            Status.name as status_name, Status.color, Delivery.name as delivery_name, Payment.name as payment_name'
        );
        $c->groupby($this->classKey . '.id');

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
        $q->query['columns'] = ['SQL_CALC_FOUND_ROWS msOrder.id, fullname as customer'];
        $sortClassKey = $this->getSortClassKey();
        $sortKey = $this->modx->getSelectColumns($sortClassKey, $this->getProperty('sortAlias', $sortClassKey), '', [$this->getProperty('sort')]);
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
        $c->query['where'] = [[
            new xPDOQueryCondition(['sql' => 'msOrder.id IN ' . $ids, 'conjunction' => 'AND']),
        ]];
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
        $ms3 = $this->modx->services->get('ms3');
        if (empty($data['customer'])) {
            $data['customer'] = $data['customer_username'];
        }
        if (isset($data['cost'])) {
            $data['cost'] = $ms3->format->price($data['cost']);
        }
        if (isset($data['cart_cost'])) {
            $data['cart_cost'] = $ms3->format->price($data['cart_cost']);
        }
        if (isset($data['delivery_cost'])) {
            $data['delivery_cost'] = $ms3->format->price($data['delivery_cost']);
        }
        if (isset($data['weight'])) {
            $data['weight'] = $ms3->format->weight($data['weight']);
        }

        $data['actions'] = [
            [
                'cls' => '',
                'icon' => 'icon icon-edit',
                'title' => $this->modx->lexicon('ms_menu_update'),
                'action' => 'updateOrder',
                'button' => true,
                'menu' => true,
            ],
            [
                'cls' => [
                    'menu' => 'red',
                    'button' => 'red',
                ],
                'icon' => 'icon icon-trash-o',
                'title' => $this->modx->lexicon('ms_menu_remove'),
                'multiple' => $this->modx->lexicon('ms_menu_remove_multiple'),
                'action' => 'removeOrder',
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

        $selected = $this->query;
        $selected->query['columns'] = [];
        $selected->query['limit'] =
        $selected->query['offset'] = 0;
        $selected->select('SUM(msOrder.cost)');
        $selected->prepare();
        $selected->stmt->execute();

        $month = $this->modx->newQuery($this->classKey);
        $statuses = [2, 3];
        $month->where(['status_id:IN' => $statuses]);
        $month->where('createdon BETWEEN NOW() - INTERVAL 30 DAY AND NOW()');
        $month->select('SUM(msOrder.cost) as sum, COUNT(msOrder.id) as total');
        $month->prepare();
        $month->stmt->execute();
        $month = $month->stmt->fetch(\PDO::FETCH_ASSOC);

        $data = [
            'success' => true,
            'results' => $array,
            'total' => $count,
            'num' => number_format($count, 0, '.', ' '),
            'sum' => number_format(round($selected->stmt->fetchColumn()), 0, '.', ' '),
            'month_sum' => number_format(round($month['sum']), 0, '.', ' '),
            'month_total' => number_format($month['total'], 0, '.', ' '),
        ];

        return json_encode($data);
    }
}
