<?php

namespace MiniShop3\Model;

use MiniShop3\Services\Order\OrderService;
use MODX\Revolution\modSystemEvent;
use MODX\Revolution\modX;
use xPDO\Om\xPDOSimpleObject;

/**
 * Class msOrder
 *
 * @property integer $user_id
 * @property integer $customer_id
 * @property string $token
 * @property string $createdon
 * @property string $updatedon
 * @property string $num
 * @property float $cost
 * @property float $cart_cost
 * @property float $delivery_cost
 * @property float $weight
 * @property integer $status_id
 * @property integer $delivery_id
 * @property integer $payment_id
 * @property string $context
 * @property string $order_comment
 * @property array $properties
 *
 * @property msOrderAddress $Address
 * @property msOrderProduct[] $Products
 * @property msOrderLog[] $Log
 *
 * @package MiniShop3\Model
 */
class msOrder extends xPDOSimpleObject
{
    /** @var OrderService|null */
    protected $orderService;
    /**
     * @return bool
     */
    public function updateProducts()
    {
        return $this->getOrderService()->updateProducts($this);
    }

    public function save($cacheFlag = null)
    {
        $isNew = $this->isNew();

        // Событие BEFORE
        if ($this->xpdo instanceof modX) {
            $this->xpdo->invokeEvent('msOnBeforeSaveOrder', [
                'mode' => $isNew ? modSystemEvent::MODE_NEW : modSystemEvent::MODE_UPD,
                'object' => $this,
                'msOrder' => $this,
                'cacheFlag' => $cacheFlag,
            ]);
        }

        // Вызов родительского save()
        $saved = parent::save($cacheFlag);

        // Событие AFTER
        if ($saved && $this->xpdo instanceof modX) {
            $this->xpdo->invokeEvent('msOnSaveOrder', [
                'mode' => $isNew ? modSystemEvent::MODE_NEW : modSystemEvent::MODE_UPD,
                'object' => $this,
                'msOrder' => $this,
                'cacheFlag' => $cacheFlag,
            ]);
        }

        return $saved;
    }

    public function remove(array $ancestors = [])
    {
        // Событие BEFORE
        if ($this->xpdo instanceof modX) {
            $this->xpdo->invokeEvent('msOnBeforeRemoveOrder', [
                'id' => $this->get('id'),
                'object' => $this,
                'msOrder' => $this,
                'ancestors' => $ancestors,
            ]);
        }

        // Вызов родительского remove()
        $removed = parent::remove($ancestors);

        // Событие AFTER
        if ($removed && $this->xpdo instanceof modX) {
            $this->xpdo->invokeEvent('msOnRemoveOrder', [
                'id' => $this->get('id'),
                'object' => $this,
                'msOrder' => $this,
                'ancestors' => $ancestors,
            ]);
        }

        return $removed;
    }

    /**
     * Получить сервис заказов (lazy loading)
     *
     * @return OrderService
     */
    protected function getOrderService(): OrderService
    {
        if ($this->orderService === null) {
            if ($this->xpdo->services->has('ms3_order_service')) {
                $this->orderService = $this->xpdo->services->get('ms3_order_service');
            } else {
                $this->orderService = new OrderService($this->xpdo);
            }
        }

        return $this->orderService;
    }
}
