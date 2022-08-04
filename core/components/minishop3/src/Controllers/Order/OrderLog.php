<?php

namespace MiniShop3\Controllers\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderLog;
use MODX\Revolution\modX;

class OrderLog
{
    /** @var modX $modx */
    public $modx;
    /** @var MiniShop3 $ms3 */
    public $ms3;

    public function __construct(MiniShop3 $ms3)
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;

        $this->modx->lexicon->load('minishop:default');
    }

    /**
     * Function for logging changes of the order
     *
     * @param integer $order_id The id of the order
     * @param string $entry The value of action
     * @param string $action The name of action made with order
     *
     * @return boolean
     */
    public function process($order_id, $entry, $action = 'status')
    {
        /** @var msOrder $order */
        $order = $this->modx->getObject(msOrder::class, ['id' => $order_id]);
        if (!$order) {
            return false;
        }

        if (empty($this->modx->request)) {
            $this->modx->getRequest();
        }

        $user_id = ($action === 'status' && $entry == 1) || !$this->modx->user->id
            ? $order->get('user_id')
            : $this->modx->user->id;
        $log = $this->modx->newObject(msOrderLog::class, [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'timestamp' => time(),
            'action' => $action,
            'entry' => $entry,
            'ip' => $this->modx->request->getClientIp(),
        ]);

        return $log->save();
    }
}
