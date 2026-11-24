<?php

namespace MiniShop3\Notifications\Order;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MODX\Revolution\modX;

/**
 * Notification for the `order_created` event.
 *
 * Sent when an order leaves the draft state and becomes a real order:
 * checkout on the storefront, finalizing a draft in the manager, or a
 * programmatic order (#811).
 *
 * The event is dispatched by {@see \MiniShop3\Services\Order\OrderStatusService}
 * right after the status change, so the order already carries its number,
 * costs and address. Reuses the config lookup, the template rendering and
 * the placeholders of {@see StatusChangedNotification}; only the event key
 * and the default messages differ.
 */
class OrderCreatedNotification extends StatusChangedNotification
{
    public const EVENT = 'order_created';

    /**
     * A created order has no previous status: the draft it replaces is not
     * a status of the order itself. The argument is kept for compatibility
     * with the scheduler task, which recreates notifications by class.
     */
    public function __construct(
        modX $modx,
        msOrder $order,
        msOrderStatus $newStatus,
        ?msOrderStatus $oldStatus = null
    ) {
        parent::__construct($modx, $order, $newStatus, null);
    }

    /**
     * Default Telegram message for a newly created order.
     */
    protected function buildDefaultTelegramMessage(string $recipientType): string
    {
        $siteName = $this->modx->getOption('site_name');
        $orderNum = $this->order->get('num');
        $newOrder = $this->modx->lexicon('ms3_telegram_new_order');

        if ($recipientType === 'customer') {
            $template = "🛒 <b>{$siteName}</b>\n\n";
            $template .= "{$newOrder} <b>#{$orderNum}</b>";

            return $template;
        }

        $cost = $this->order->get('cost');

        $template = "📦 <b>{$newOrder}</b>\n\n";
        $template .= $this->modx->lexicon('ms3_telegram_order') . " <b>#{$orderNum}</b>";
        $template .= "\n" . $this->modx->lexicon('ms3_telegram_total') . ": <b>{$cost}</b>";

        return $template;
    }

    /**
     * Default SMS message for a newly created order.
     */
    protected function buildDefaultSmsMessage(): string
    {
        $orderNum = $this->order->get('num');
        $newOrder = $this->modx->lexicon('ms3_telegram_new_order');

        return "{$newOrder} #{$orderNum}";
    }
}
