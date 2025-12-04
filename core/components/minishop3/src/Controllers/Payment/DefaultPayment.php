<?php

namespace MiniShop3\Controllers\Payment;

use MiniShop3\Model\msOrder;

/**
 * Default payment provider (stub)
 *
 * Used for payment methods without external payment system integration:
 * - Cash on delivery
 * - Invoice payment
 * - Bank transfer
 * - etc.
 *
 * Simply marks order as pending payment and redirects to success page.
 *
 * @package MiniShop3\Controllers\Payment
 */
class DefaultPayment extends Payment
{
    /**
     * Send order (stub)
     *
     * For default provider just returns link to success page
     * without real payment system interaction.
     *
     * @param msOrder $order Order for payment
     * @return array Response with link to success page
     */
    public function send(msOrder $order): array
    {
        $successPageId = (int)$this->modx->getOption('ms3_order_success_page_id', null, 0);

        if (empty($successPageId)) {
            $successPageId = $this->modx->getOption('site_start');
        }

        $paymentLink = $this->modx->makeUrl($successPageId, '', [
            'msorder' => $order->get('num'),
        ], 'full');

        return $this->success('ms3_payment_link_created', [
            'payment_link' => $paymentLink,
            'order_id' => $order->get('id'),
            'order_num' => $order->get('num'),
        ]);
    }

    /**
     * Process callback (stub)
     *
     * For default provider callback processing is not required
     * since there is no external payment system.
     *
     * @param msOrder $order Order to check
     * @return array Success response
     */
    public function receive(msOrder $order): array
    {
        return $this->success('ms3_payment_received', [
            'order_id' => $order->get('id'),
            'order_num' => $order->get('num'),
        ]);
    }
}
