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

        // Filter by visibility (for customer-facing views)
        $visibleOnly = $this->getProperty('visible_only');
        if ($visibleOnly !== null && $visibleOnly !== '') {
            $c->where(['visible' => (bool)$visibleOnly]);
        }

        // Filter by action type
        $action = $this->getProperty('action');
        if (!empty($action)) {
            $c->where(['action' => $action]);
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
        // Process entry - decode JSON if needed
        $entryData = null;
        if (isset($data['entry'])) {
            if (is_string($data['entry'])) {
                $decoded = json_decode($data['entry'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $entryData = $decoded;
                }
            } elseif (is_array($data['entry'])) {
                $entryData = $data['entry'];
            }
        }

        // Store decoded entry data
        $data['entry_data'] = $entryData;

        // Handle different action types
        if ($data['action'] === msOrderLog::ACTION_STATUS) {
            // New JSON format: {old_status_id, new_status_id, old_status_name, new_status_name}
            if ($entryData && isset($entryData['new_status_id'])) {
                $newStatus = $this->modx->getObject(msOrderStatus::class, $entryData['new_status_id']);
                if ($newStatus) {
                    $data['color'] = $newStatus->get('color');
                    $data['entry_display'] = $entryData['new_status_name'] ?? $newStatus->get('name');
                } else {
                    $data['entry_display'] = $entryData['new_status_name'] ?? $entryData['new_status_id'];
                }
            } elseif (is_numeric($data['entry'])) {
                // Legacy format: entry is status_id
                $q = $this->modx->newQuery(msOrderStatus::class);
                $q->where(['id' => $data['entry']]);
                $q->select('name, color');
                $q->prepare();
                $q->stmt->execute();
                $status = $q->stmt->fetch(\PDO::FETCH_ASSOC);
                if ($status) {
                    $data['color'] = $status['color'];
                    $data['entry_display'] = $status['name'];
                }
            }
        } elseif ($data['action'] === msOrderLog::ACTION_PRODUCTS) {
            // Products action: add/update/remove
            if ($entryData) {
                $operation = $entryData['operation'] ?? 'unknown';
                $productName = $entryData['product_name'] ?? '';
                $data['entry_display'] = sprintf('%s: %s', ucfirst($operation), $productName);
            }
        } elseif ($data['action'] === msOrderLog::ACTION_FIELD) {
            // Field changes
            if ($entryData && isset($entryData['fields'])) {
                $fieldNames = array_keys($entryData['fields']);
                $data['entry_display'] = 'Fields: ' . implode(', ', $fieldNames);
            }
        } elseif ($data['action'] === msOrderLog::ACTION_ADDRESS) {
            // Address changes
            if ($entryData && isset($entryData['fields'])) {
                $fieldNames = array_keys($entryData['fields']);
                $data['entry_display'] = 'Address: ' . implode(', ', $fieldNames);
            }
        } elseif ($data['action'] === msOrderLog::ACTION_PAYMENT) {
            // Payment action
            if ($entryData) {
                $operation = $entryData['operation'] ?? 'unknown';
                $amount = $entryData['amount'] ?? 0;
                $data['entry_display'] = sprintf('%s: %s', ucfirst($operation), $amount);
            }
        }

        // Format entry with color if available
        if (!empty($data['color']) && !empty($data['entry_display'])) {
            $data['entry_formatted'] = '<span style="color:' . $data['color'] . '">' . $data['entry_display'] . '</span>';
        } else {
            $data['entry_formatted'] = $data['entry_display'] ?? $data['entry'];
        }

        return $data;
    }
}
