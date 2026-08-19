<?php

namespace MiniShop3\Notifications;

use MODX\Revolution\modX;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\Payment\PaymentPublicFields;
use MiniShop3\Notifications\Messages\EmailMessage;
use MiniShop3\Notifications\Messages\TelegramMessage;
use MiniShop3\Notifications\Messages\SmsMessage;

/**
 * Base class for all notifications
 *
 * Extend this class to create specific notifications
 * (e.g., OrderStatusChanged, OrderCreated, etc.)
 */
abstract class Notification
{
    protected modX $modx;
    protected MiniShop3 $ms3;
    protected msOrder $order;
    protected array $data = [];

    public function __construct(modX $modx, msOrder $order, array $data = [])
    {
        $this->modx = $modx;
        $this->ms3 = $modx->services->get('ms3');
        $this->order = $order;
        $this->data = $data;
    }

    /**
     * Get channels for this notification
     *
     * @param string $recipientType 'customer' or 'manager'
     * @return string[] Array of channel names ['email', 'telegram']
     */
    abstract public function via(string $recipientType): array;

    /**
     * Build email message for this notification
     *
     * @param string $recipientType 'customer' or 'manager'
     * @return EmailMessage|null
     */
    public function toEmail(string $recipientType): ?EmailMessage
    {
        return null;
    }

    /**
     * Build Telegram message for this notification
     *
     * @param string $recipientType 'customer' or 'manager'
     * @return TelegramMessage|null
     */
    public function toTelegram(string $recipientType): ?TelegramMessage
    {
        return null;
    }

    /**
     * Build SMS message for this notification
     *
     * @param string $recipientType 'customer' or 'manager'
     * @return SmsMessage|null
     */
    public function toSms(string $recipientType): ?SmsMessage
    {
        return null;
    }

    /**
     * Should this notification be queued via Scheduler?
     *
     * @return bool
     */
    public function shouldQueue(): bool
    {
        return (bool) $this->modx->getOption('ms3_use_scheduler', null, false);
    }

    /**
     * Get delay in seconds before sending (0 = immediate)
     *
     * @return int
     */
    public function delay(): int
    {
        return 0;
    }

    /**
     * Get the order associated with this notification
     *
     * @return msOrder
     */
    public function getOrder(): msOrder
    {
        return $this->order;
    }

    /**
     * Get notification data/context
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Prepare placeholders for templates
     *
     * @return array
     */
    public function getPlaceholders(): array
    {
        $orderData = $this->order->toArray();

        // Format prices and weight for display
        $formattedCost = $this->ms3->format->price($orderData['cost'] ?? 0);
        $formattedCartCost = $this->ms3->format->price($orderData['cart_cost'] ?? 0);
        $formattedDeliveryCost = $this->ms3->format->price($orderData['delivery_cost'] ?? 0);
        $formattedWeight = $this->ms3->format->weight($orderData['weight'] ?? 0);

        // Pre-formatted with currency/unit for use in email templates
        $formattedCostWithCurrency = $this->ms3->format->price($orderData['cost'] ?? 0, true);
        $formattedCartCostWithCurrency = $this->ms3->format->price($orderData['cart_cost'] ?? 0, true);
        $formattedDeliveryCostWithCurrency = $this->ms3->format->price($orderData['delivery_cost'] ?? 0, true);
        $formattedWeightWithUnit = $this->ms3->format->weightWithUnit($orderData['weight'] ?? 0);

        // Start with order data spread (for backwards compatibility)
        $pls = $orderData;
        $pls['cost'] = $formattedCost;
        $pls['cart_cost'] = $formattedCartCost;
        $pls['delivery_cost'] = $formattedDeliveryCost;
        $pls['weight'] = $formattedWeight;
        $pls['cost_formatted'] = $formattedCostWithCurrency;
        $pls['cart_cost_formatted'] = $formattedCartCostWithCurrency;
        $pls['delivery_cost_formatted'] = $formattedDeliveryCostWithCurrency;
        $pls['weight_formatted'] = $formattedWeightWithUnit;

        // Also add as nested 'order' array (for templates using {$order.num} syntax)
        $pls['order'] = $orderData;
        $pls['order']['cost'] = $formattedCost;
        $pls['order']['cart_cost'] = $formattedCartCost;
        $pls['order']['delivery_cost'] = $formattedDeliveryCost;
        $pls['order']['weight'] = $formattedWeight;
        $pls['order']['cost_formatted'] = $formattedCostWithCurrency;
        $pls['order']['cart_cost_formatted'] = $formattedCartCostWithCurrency;
        $pls['order']['delivery_cost_formatted'] = $formattedDeliveryCostWithCurrency;
        $pls['order']['weight_formatted'] = $formattedWeightWithUnit;

        // Add customer data
        if ($customer = $this->order->getOne('Customer')) {
            $pls['customer'] = $customer->toArray();
        }

        // Add address data
        if ($address = $this->order->getOne('Address')) {
            $pls['address'] = $address->toArray();
        }

        // Add delivery data
        if ($delivery = $this->order->getOne('Delivery')) {
            $pls['delivery'] = $delivery->toArray();
        }

        // Add payment data
        if ($payment = $this->order->getOne('Payment')) {
            $pls['payment'] = PaymentPublicFields::fromEntityOrEmpty($payment);
        }

        // Add products from order
        $pls['products'] = $this->getOrderProducts();

        // Add totals for email template
        $pls['total'] = [
            'cost' => $formattedCost,
            'cost_formatted' => $formattedCostWithCurrency,
            'cart_cost' => $formattedCartCost,
            'cart_cost_formatted' => $formattedCartCostWithCurrency,
            'cart_count' => $orderData['cart_count'] ?? 0,
            'cart_weight' => $formattedWeight,
            'cart_weight_formatted' => $formattedWeightWithUnit,
            'delivery_cost' => $formattedDeliveryCost,
            'delivery_cost_formatted' => $formattedDeliveryCostWithCurrency,
        ];

        // Merge custom data
        $pls = array_merge($pls, $this->data);

        return $pls;
    }

    /**
     * Get products from order for email template
     *
     * @return array
     */
    protected function getOrderProducts(): array
    {
        $products = [];
        $productIds = [];

        /** @var \MiniShop3\Model\msOrderProduct $orderProduct */
        foreach ($this->order->getMany('Products') as $orderProduct) {
            $productIds[] = (int) $orderProduct->get('product_id');
        }
        $productIds = array_unique($productIds);

        // Batch load product data for old_price
        $dataMap = [];
        if (!empty($productIds)) {
            $dataQuery = $this->modx->newQuery(msProductData::class);
            $dataQuery->where(['id:IN' => $productIds]);
            foreach ($this->modx->getIterator(msProductData::class, $dataQuery) as $data) {
                $dataMap[$data->get('id')] = [
                    'original_price' => (float) $data->get('price'),
                    'old_price' => (float) $data->get('old_price'),
                ];
            }
        }

        /** @var \MiniShop3\Model\msOrderProduct $orderProduct */
        foreach ($this->order->getMany('Products') as $orderProduct) {
            $productData = $orderProduct->toArray();
            $pid = (int) $productData['product_id'];

            // Add product resource data if available
            if ($product = $orderProduct->getOne('Product')) {
                $productData['pagetitle'] = $product->get('pagetitle');
                $productData['thumb'] = $product->get('thumb');
            }

            // Format price
            $rawPrice = (float) ($productData['price'] ?? 0);
            $rawCost = (float) ($productData['cost'] ?? 0);
            $rawWeight = (float) ($productData['weight'] ?? 0);

            $originalPrice = $dataMap[$pid]['original_price'] ?? 0;
            $catalogOldPrice = $dataMap[$pid]['old_price'] ?? 0;
            $old_price = $originalPrice > $rawPrice ? $originalPrice : $catalogOldPrice;
            $discount_price = $old_price > 0 ? $old_price - $rawPrice : 0;

            $productData['price'] = $this->ms3->format->price($rawPrice);
            $productData['cost'] = $this->ms3->format->price($rawCost);
            $productData['weight'] = $this->ms3->format->weight($rawWeight);
            $productData['price_formatted'] = $this->ms3->format->price($rawPrice, true);
            $productData['old_price_formatted'] = $old_price > 0 && $old_price > $rawPrice
                ? $this->ms3->format->price($old_price, true)
                : '';
            $productData['cost_formatted'] = $this->ms3->format->price($rawCost, true);
            $productData['old_cost_formatted'] = $old_price > 0 && $old_price > $rawPrice
                ? $this->ms3->format->price(($productData['count'] ?? 0) * $old_price, true)
                : '';
            $productData['discount_price_formatted'] = $discount_price > 0
                ? $this->ms3->format->price($discount_price, true)
                : '';
            $productData['discount_cost_formatted'] = $discount_price > 0
                ? $this->ms3->format->price(($productData['count'] ?? 0) * $discount_price, true)
                : '';
            $productData['weight_formatted'] = $this->ms3->format->weightWithUnit($rawWeight);

            // Parse options if stored as JSON
            if (!empty($productData['options']) && is_string($productData['options'])) {
                $options = json_decode($productData['options'], true);
                $productData['options'] = is_array($options) ? $options : [];
            }

            $products[] = $productData;
        }

        return $products;
    }

    /**
     * Get notification type identifier
     *
     * @return string
     */
    public function getType(): string
    {
        $class = get_class($this);
        $parts = explode('\\', $class);
        return end($parts);
    }
}
