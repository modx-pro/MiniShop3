<?php

namespace MiniShop3\Controllers\Delivery;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Order\OrderCostEngine;
use MiniShop3\Services\Order\OrderService;
use MODX\Revolution\modX;

/**
 * Base abstract class for delivery providers
 *
 * Provides common functionality for all delivery methods.
 * Custom providers (CDEK, Russian Post, DPD, etc.) can inherit
 * this class and override getCost() method for their calculation logic.
 * Async tracking/webhooks use optional ShipmentProviderInterface;
 * cost-only classes stay on DeliveryProviderInterface only.
 *
 * Example of creating a CDEK provider:
 * ```php
 * class CdekDelivery extends Delivery {
 *     public function getCost(msOrder $order, msDelivery $delivery, float $cost): float {
 *         $address = $order->getOne('Address');
 *         $cityTo = $address->get('city');
 *
 *         // Call CDEK API to calculate cost
 *         $cdekApi = new CdekApiClient();
 *         $tariff = $cdekApi->calculate([
 *             'city_to' => $cityTo,
 *             'weight' => $order->get('weight'),
 *             'packages' => $this->getPackagesFromOrder($order)
 *         ]);
 *
 *         return (float)$tariff['delivery_sum'];
 *     }
 * }
 * ```
 *
 * @package MiniShop3\Controllers\Delivery
 */
abstract class Delivery implements DeliveryProviderInterface
{
    /** @var modX */
    protected modX $modx;

    /** @var MiniShop3 */
    protected MiniShop3 $ms3;

    /** @var array */
    protected array $config = [];

    /**
     * @param MiniShop3 $ms3
     * @param array $config
     */
    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $this->config = $config;
    }

    /**
     * Calculate delivery cost (default implementation)
     *
     * Standard calculation logic includes:
     * - Cost by weight (weight_price * weight of the order products)
     * - Free delivery when threshold exceeded (free_delivery_amount)
     * - Fixed cost or percentage of order amount
     *
     * Custom providers can override this method for
     * integration with external APIs (CDEK, Russian Post, etc.)
     *
     * @param msOrder $order Order for delivery calculation
     * @param msDelivery $delivery Delivery method
     * @param float $cost Current order cost (not used in default implementation, but may be needed by custom providers)
     * @return float Additional delivery cost
     */
    public function getCost(msOrder $order, msDelivery $delivery, float $cost): float
    {
        // Weight of the order's own products (per-unit weight × count). The cart controller
        // is not used: outside a storefront session it has no customer token.
        $orderWeight = OrderService::aggregateProductsTotals($order->getMany('Products') ?? [])['weight'];

        return OrderCostEngine::calculateDefaultDeliveryCost(
            $this->modx,
            $delivery,
            $cost,
            $orderWeight
        );
    }

    /**
     * Helper method to return error response
     *
     * @param string $message Lexicon key
     * @param array $data Additional data
     * @param array $placeholders Placeholders for message
     * @return array Array with success=false
     */
    protected function error(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * Helper method to return success response
     *
     * @param string $message Lexicon key
     * @param array $data Additional data
     * @param array $placeholders Placeholders for message
     * @return array Array with success=true
     */
    protected function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }
}
