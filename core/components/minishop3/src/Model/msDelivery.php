<?php

namespace MiniShop3\Model;

use MiniShop3\Controllers\Delivery\DeliveryProviderInterface;
use MiniShop3\MiniShop3;
use MiniShop3\Services\Delivery\DeliveryService;
use MODX\Revolution\modX;
use xPDO\Om\xPDOSimpleObject;
use xPDO\xPDO;

/**
 * Class msDelivery
 *
 * @property string $name
 * @property string $description
 * @property string $price
 * @property float $weight_price
 * @property float $distance_price
 * @property string $logo
 * @property integer $position
 * @property integer $active
 * @property string $class
 * @property array $properties
 * @property string $validation_rules
 * @property float $free_delivery_amount
 *
 * @package MiniShop3\Model
 */
class msDelivery extends xPDOSimpleObject
{
    /** @var DeliveryProviderInterface|null $controller */
    public $controller;

    /** @var MiniShop3 $ms3 */
    public $ms3;

    /** @var DeliveryService|null */
    protected $deliveryService;

    /**
     * msDelivery constructor.
     *
     * @param xPDO $xpdo
     */
    public function __construct(xPDO $xpdo)
    {
        parent::__construct($xpdo);
        if ($this->xpdo->services->has('ms3')) {
            $this->ms3 = $this->xpdo->services->get('ms3');
        }
    }

    /**
     * Loads delivery controller class
     *
     * @return bool
     */
    public function loadController()
    {
        $this->controller = $this->getDeliveryService()->loadDeliveryController($this);
        return $this->controller instanceof DeliveryProviderInterface;
    }

    /**
     * Returns an additional cost depending on the method of delivery
     *
     * @param msOrder $order Order object
     * @param float $cost Current cost of order
     *
     * @return float
     */
    public function getCost(msOrder $order, float $cost = 0.0): float
    {
        return $this->getDeliveryService()->calculateDeliveryCost(
            $this,
            $this->controller,
            $order,
            (float)$cost
        );
    }

    /**
     * Returns id of first active payment method for this delivery
     *
     * @return int|mixed
     */
    public function getFirstPayment()
    {
        return $this->getDeliveryService()->getFirstActivePayment($this);
    }

    /**
     * @param array $ancestors
     *
     * @return bool
     */
    public function remove(array $ancestors = [])
    {
        $this->getDeliveryService()->removeDelivery($this, $ancestors);
        return parent::remove($ancestors);
    }

    /**
     * Получить сервис доставки (lazy loading)
     *
     * @return DeliveryService
     */
    protected function getDeliveryService(): DeliveryService
    {
        if ($this->deliveryService === null) {
            if ($this->xpdo->services->has('ms3_delivery_service')) {
                $this->deliveryService = $this->xpdo->services->get('ms3_delivery_service');
            } else {
                $this->deliveryService = new DeliveryService($this->xpdo);
            }
        }

        return $this->deliveryService;
    }
}
