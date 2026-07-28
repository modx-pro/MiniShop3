<?php

namespace MiniShop3\Services\Delivery;

use MiniShop3\Controllers\Delivery\DeliveryProviderInterface;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;

/**
 * Service for working with delivery
 *
 * Handles business logic related to order delivery,
 * including loading controllers, cost calculation and managing relations
 */
class DeliveryService
{
    /** @var modX */
    protected $modx;

    /** @var MiniShop3|null */
    protected $ms3;

    /** @var string */
    protected $defaultControllerClass = 'MiniShop3\\Controllers\\Delivery\\DefaultDelivery';

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        if ($modx->services->has('ms3')) {
            $this->ms3 = $modx->services->get('ms3');
        }
    }

    /**
     * Load delivery controller
     *
     * Creates delivery controller instance based on class from settings.
     * If class not specified, uses default controller (DefaultDelivery).
     *
     * @param msDelivery $delivery
     * @return DeliveryProviderInterface|null Delivery controller or null on error
     */
    public function loadDeliveryController(msDelivery $delivery): ?DeliveryProviderInterface
    {
        $class = $delivery->get('class');
        if (empty($class)) {
            $class = $this->defaultControllerClass;
        }

        try {
            $controller = new $class($this->ms3, []);

            if (!$controller instanceof DeliveryProviderInterface) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    sprintf(
                        'DeliveryService: Class "%s" does not implement DeliveryProviderInterface for delivery ID=%d',
                        $class,
                        $delivery->get('id')
                    )
                );
                return null;
            }

            return $controller;
        } catch (\Throwable $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                sprintf(
                    'DeliveryService: Error loading delivery controller "%s": %s',
                    $class,
                    $e->getMessage()
                )
            );
            return null;
        }
    }

    /**
     * Calculate delivery cost
     *
     * Delegates cost calculation to delivery controller.
     * Controller can consider weight, distance, order cost and other factors.
     *
     * @param msDelivery $delivery Delivery method
     * @param DeliveryProviderInterface|null $controller Delivery controller (if null - will be loaded)
     * @param msOrder $order Order
     * @param float $cost Current order cost
     * @return float Delivery cost
     */
    public function calculateDeliveryCost(
        msDelivery $delivery,
        ?DeliveryProviderInterface $controller,
        msOrder $order,
        float $cost = 0.0
    ): float {
        if (!$controller instanceof DeliveryProviderInterface) {
            $controller = $this->loadDeliveryController($delivery);
            if (!$controller) {
                return 0.0;
            }
        }

        return (float)$controller->getCost($order, $delivery, $cost);
    }

    /**
     * Check whether payment method is linked to delivery and active.
     */
    public function isPaymentAvailableForDelivery(int $deliveryId, int $paymentId): bool
    {
        if ($deliveryId <= 0 || $paymentId <= 0) {
            return false;
        }

        $payment = $this->modx->getObject(msPayment::class, [
            'id' => $paymentId,
            'active' => 1,
        ]);
        if (!$payment) {
            return false;
        }

        return (bool) $this->modx->getCount(msDeliveryMember::class, [
            'delivery_id' => $deliveryId,
            'payment_id' => $paymentId,
        ]);
    }

    /**
     * Lexicon key when payment is not linked to delivery, or null if pair is valid/incomplete.
     */
    public function getDeliveryPaymentPairError(int $deliveryId, int $paymentId): ?string
    {
        if ($deliveryId <= 0 || $paymentId <= 0) {
            return null;
        }

        return $this->isPaymentAvailableForDelivery($deliveryId, $paymentId)
            ? null
            : 'ms3_order_err_payment_delivery';
    }

    /**
     * Get first active payment method for delivery
     *
     * Returns ID of first active payment method
     * linked to this delivery method
     *
     * @param msDelivery $delivery
     * @return int Payment method ID or 0 if not found
     */
    public function getFirstActivePayment(msDelivery $delivery): int
    {
        $deliveryId = $delivery->get('id');

        $query = $this->modx->newQuery(msPayment::class);
        $query->leftJoin(msDeliveryMember::class, 'Member', msPayment::class . '.id = Member.payment_id');
        $query->leftJoin(msDelivery::class, 'Delivery', 'Member.delivery_id = Delivery.id');
        $query->sortby(msPayment::class . '.id', 'ASC');
        $query->select(msPayment::class . '.id');
        $query->where([
            msPayment::class . '.active' => 1,
            'Delivery.id' => $deliveryId
        ]);
        $query->limit(1);

        if ($query->prepare() && $query->stmt->execute()) {
            $paymentId = $query->stmt->fetchColumn();
            return $paymentId ? (int)$paymentId : 0;
        }

        return 0;
    }

    /**
     * Remove delivery with relation cleanup
     *
     * Removes all delivery relations with payment methods
     * from msDeliveryMember table before delivery removal
     *
     * @param msDelivery $delivery
     * @param array $ancestors
     * @return bool
     */
    public function removeDelivery(msDelivery $delivery, array $ancestors = []): bool
    {
        $deliveryId = $delivery->get('id');

        $this->modx->removeCollection(msDeliveryMember::class, [
            'delivery_id' => $deliveryId
        ]);

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            sprintf(
                'DeliveryService: Removed DeliveryMember relations for delivery ID=%d "%s"',
                $deliveryId,
                $delivery->get('name')
            )
        );

        return true;
    }

    /**
     * Get list of available payment methods for delivery
     *
     * Returns array of active payment methods
     * linked to this delivery method
     *
     * @param msDelivery $delivery
     * @return array Array of msPayment objects
     */
    public function getAvailablePayments(msDelivery $delivery): array
    {
        $deliveryId = $delivery->get('id');

        $query = $this->modx->newQuery(msPayment::class);
        $query->leftJoin(msDeliveryMember::class, 'Member', msPayment::class . '.id = Member.payment_id');
        $query->where([
            msPayment::class . '.active' => 1,
            'Member.delivery_id' => $deliveryId
        ]);
        $query->sortby(msPayment::class . '.position', 'ASC');

        return $this->modx->getCollection(msPayment::class, $query);
    }
}
