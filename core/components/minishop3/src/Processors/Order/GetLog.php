<?php

namespace MiniShop3\Processors\Order;

use MiniShop3\Model\msOrderLog;
use MiniShop3\Model\msOrderStatus;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOQuery;

class GetLog extends GetListProcessor
{
    public $classKey = msOrderLog::class;
    public $languageTopics = ['default', 'minishop3:manager'];
    public $defaultSortField = 'id';
    public $defaultSortDirection = 'DESC';
    public $permission = 'msorder_view';


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

    /** {@inheritDoc} */
    public function getData()
    {
        $data = [];
        $limit = intval($this->getProperty('limit'));
        $start = intval($this->getProperty('start'));

        /* query for chunks */
        $c = $this->modx->newQuery($this->classKey);
        $c = $this->prepareQueryBeforeCount($c);
        $data['total'] = $this->modx->getCount($this->classKey, $c);
        $c = $this->prepareQueryAfterCount($c);

        $sortClassKey = $this->getSortClassKey();
        $sortKey = $this->modx->getSelectColumns(
            $sortClassKey,
            $this->getProperty('sortAlias', 'msOrderLog'),
            '',
            [$this->getProperty('sort')]
        );
        if (empty($sortKey)) {
            $sortKey = $this->getProperty('sort');
        }
        $c->sortby($sortKey, $this->getProperty('dir'));
        if ($limit > 0) {
            $c->limit($limit, $start);
        }

        if ($c->prepare() && $c->stmt->execute()) {
            $data['results'] = $c->stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        return $data;
    }

    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $order_id = $this->getProperty('order_id');
        if (!empty($order_id)) {
            $c->where([
                'order_id' => $order_id
            ]);
        }

        $c->leftJoin(modUser::class, 'modUser', '`msOrderLog`.`user_id` = `modUser`.`id`');
        $c->leftJoin(modUserProfile::class, 'modUserProfile', '`msOrderLog`.`user_id` = `modUserProfile`.`internalKey`');
        $exclude = [];
        $add_select = ' , `modUser`.`username`, `modUserProfile`.`fullname`';
        $select = $this->modx->getSelectColumns(msOrderLog::class, 'msOrderLog', '', $exclude, true);
        $select .= $add_select;

        $c->select($select);

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
        if ($data['action'] === 'status') {
            $q = $this->modx->newQuery(msOrderStatus::class);
            $q->where(['id' => $data['entry']]);
            $q->select('name as entry, color');
            $q->prepare();
            $q->stmt->execute();
            $status = $q->stmt->fetch(\PDO::FETCH_ASSOC);
            $data = array_merge($data, $status);
        }
        if (!empty($data['color'])) {
            $data['entry'] = '<span>' . $data['entry'] . '</span>';
        }

        return $data;
    }
}
