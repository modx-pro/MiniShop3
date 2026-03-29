<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderLog;
use MODX\Revolution\modX;

/**
 * Order Log Service
 *
 * Handles logging of order changes: status updates, field changes,
 * address modifications, product changes, etc.
 *
 * Can be overridden via DI to customize logging behavior
 * (e.g., send to external CRM, analytics, etc.)
 */
class OrderLogService
{
    protected modX $modx;
    protected MiniShop3 $ms3;

    /** @var array|null Cached allowed actions */
    protected ?array $allowedActions = null;

    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;

        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * Check if action should be logged based on system settings
     *
     * Setting: ms3_order_log_actions
     * Values: comma-separated list (status,products,field,address) or '*' for all
     *
     * @param string $action The action type to check
     * @return bool
     */
    public function shouldLog(string $action): bool
    {
        if ($this->allowedActions === null) {
            $setting = $this->modx->getOption(
                'ms3_order_log_actions',
                null,
                'status,products,field,address'
            );

            if (empty($setting)) {
                $this->allowedActions = [];
            } elseif ($setting === '*') {
                $this->allowedActions = msOrderLog::ALL_ACTIONS;
            } else {
                $this->allowedActions = array_map('trim', explode(',', $setting));
            }
        }

        return in_array($action, $this->allowedActions, true);
    }

    /**
     * Add log entry with structured data
     *
     * @param int $orderId Order ID
     * @param string $action Action type (use msOrderLog::ACTION_* constants)
     * @param array $data Structured data for entry (will be JSON encoded)
     * @param bool $visible Show to customer (true) or manager only (false)
     * @return bool
     */
    public function addEntry(int $orderId, string $action, array $data, bool $visible = true): bool
    {
        if (!$this->shouldLog($action)) {
            return false;
        }

        $msOrder = $this->modx->getObject(msOrder::class, ['id' => $orderId]);
        if (!$msOrder) {
            return false;
        }

        if (!$this->modx->request) {
            $this->modx->getRequest();
        }

        $userId = $this->modx->user->id ?: $msOrder->get('user_id');

        $msOrderLog = $this->modx->newObject(msOrderLog::class, [
            'order_id' => $orderId,
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'entry' => $data,
            'visible' => $visible,
            'ip' => $this->modx->request->getClientIp(),
        ]);

        return $msOrderLog->save();
    }

    /**
     * Add log entry (legacy method for backward compatibility)
     *
     * @param int $order_id The id of the order
     * @param mixed $entry The value of action (string or array)
     * @param string $action The name of action made with order
     * @param bool $visible Show to customer (true) or manager only (false)
     * @return bool
     */
    public function add(int $order_id, mixed $entry, string $action, bool $visible = true): bool
    {
        if (!$this->shouldLog($action)) {
            return false;
        }

        $msOrder = $this->modx->getObject(msOrder::class, ['id' => $order_id]);
        if (!$msOrder) {
            return false;
        }

        if (!$this->modx->request) {
            $this->modx->getRequest();
        }

        $user_id = ($action === msOrderLog::ACTION_STATUS && $entry == 1) || !$this->modx->user->id
            ? $msOrder->get('user_id')
            : $this->modx->user->id;

        // Convert legacy entry values to array for JSON storage
        if (!is_array($entry)) {
            if ($action === msOrderLog::ACTION_STATUS) {
                $entry = ['status_id' => $entry];
            } else {
                $entry = ['value' => $entry];
            }
        }

        $msOrderLog = $this->modx->newObject(msOrderLog::class, [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'entry' => $entry,
            'visible' => $visible,
            'ip' => $this->modx->request->getClientIp(),
        ]);

        return $msOrderLog->save();
    }

    /**
     * Get log entries for order
     *
     * @param int $orderId Order ID
     * @param bool $visibleOnly Only return entries visible to customer
     * @param int $limit Max entries to return (0 = all)
     * @return array Log entries
     */
    public function getEntries(int $orderId, bool $visibleOnly = false, int $limit = 0): array
    {
        $criteria = ['order_id' => $orderId];

        if ($visibleOnly) {
            $criteria['visible'] = true;
        }

        $query = $this->modx->newQuery(msOrderLog::class, $criteria);
        $query->sortby('timestamp', 'DESC');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $entries = [];
        foreach ($this->modx->getIterator(msOrderLog::class, $query) as $log) {
            $entries[] = $log->toArray();
        }

        return $entries;
    }
}
