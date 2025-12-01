<?php

namespace MiniShop3\Notifications;

use MODX\Revolution\modX;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
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
        $pls = $this->order->toArray();

        // Format prices and weight
        $pls['cost'] = $this->ms3->format->price($pls['cost'] ?? 0);
        $pls['cart_cost'] = $this->ms3->format->price($pls['cart_cost'] ?? 0);
        $pls['delivery_cost'] = $this->ms3->format->price($pls['delivery_cost'] ?? 0);
        $pls['weight'] = $this->ms3->format->weight($pls['weight'] ?? 0);

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
            $pls['payment'] = $payment->toArray();
        }

        // Merge custom data
        $pls = array_merge($pls, $this->data);

        return $pls;
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
