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
        return $this->getOrderService()->handleOrderSave($this, $cacheFlag);
    }

    public function remove(array $ancestors = [])
    {
        return $this->getOrderService()->removeOrder($this, $ancestors);
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
