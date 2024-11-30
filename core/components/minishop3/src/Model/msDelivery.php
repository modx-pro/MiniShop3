<?php

namespace MiniShop3\Model;

use MiniShop3\Controllers\Delivery\Delivery;
use MiniShop3\Controllers\Delivery\DeliveryInterface;
use MiniShop3\Controllers\Order\OrderInterface;
use MiniShop3\MiniShop3;
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
    /** @var Delivery $controller */
    public $controller;

    /** @var MiniShop3 $ms3 */
    public $ms3;

    /** @var string $defaultControllerClass */
    private string $defaultControllerClass = 'MiniShop3\\Controllers\\Delivery\\Delivery';

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
        $class = $this->get('class');
        if (!$class || $class === 'Delivery') {
            $class = $this->defaultControllerClass;
        }

        $this->controller = new $class($this->ms3, []);
        if (!$this->controller instanceof DeliveryInterface) {
            $this->xpdo->log(modX::LOG_LEVEL_ERROR, 'Could not initialize delivery controller class: "' . $class . '"');

            return false;
        }

        return true;
    }

    /**
     * Returns an additional cost depending on the method of delivery
     *
     * @param OrderInterface $order
     * @param float $cost Current cost of order
     *
     * @return float
     */
    public function getCost(OrderInterface $order, $cost = 0.0)
    {
        if (!is_object($this->controller) || !($this->controller instanceof DeliveryInterface)) {
            if (!$this->loadController()) {
                return 0.0;
            }
        }
        return $this->controller->getCost($order, $this, $cost);
    }

    /**
     * Returns id of first active payment method for this delivery
     *
     * @return int|mixed
     */
    public function getFirstPayment()
    {
        $this->modx->log(1, 'msDelivery getFirstPayment');
        $id = 0;
        $c = $this->xpdo->newQuery(msPayment::class);
        $c->leftJoin(msDeliveryMember::class, 'Member', msPayment::class . '.id = Member.payment_id');
        $c->leftJoin(msDelivery::class, 'Delivery', 'Member.delivery_id = Delivery.id');
        $c->sortby(msPayment::class . '.id', 'ASC');
        $c->select(msPayment::class . '.id');
        $c->where([msPayment::class . '.active' => 1, 'Delivery.id' => $this->id]);
        $c->limit(1);
        if ($c->prepare() && $c->stmt->execute()) {
            $id = $c->stmt->fetchColumn();
        }

        return $id;
    }

    /**
     * @param array $ancestors
     *
     * @return bool
     */
    public function remove(array $ancestors = [])
    {
        $this->xpdo->removeCollection(msDeliveryMember::class, ['delivery_id' => $this->id]);

        return parent::remove($ancestors);
    }
}
