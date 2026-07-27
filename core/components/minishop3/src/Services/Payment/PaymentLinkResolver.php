<?php

namespace MiniShop3\Services\Payment;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MODX\Revolution\modX;

/**
 * Resolves online payment URLs for order emails and storefront views.
 */
final class PaymentLinkResolver
{
    public function __construct(
        private modX $modx,
    ) {
    }

    public function shouldResolveForStatus(?msOrderStatus $status): bool
    {
        if ($status === null) {
            return false;
        }

        return self::shouldResolvePaymentLink(
            (int) $status->get('id'),
            (bool) $status->get('final'),
            (int) $this->modx->getOption('ms3_status_paid', null, 3),
            (int) $this->modx->getOption('ms3_status_new', null, 2)
        );
    }

    public static function shouldResolvePaymentLink(
        int $statusId,
        bool $isFinal,
        int $paidStatusId,
        int $newStatusId
    ): bool {
        if ($isFinal || $statusId === $paidStatusId) {
            return false;
        }

        return $statusId === $newStatusId;
    }

    public function resolveForOrder(msOrder $order, ?msOrderStatus $status = null): ?string
    {
        if ($status !== null && !$this->shouldResolveForStatus($status)) {
            return null;
        }

        $payment = $order->getOne('Payment');
        if (!$payment) {
            return null;
        }

        /** @var PaymentService $paymentService */
        $paymentService = $this->modx->services->get('ms3_payment_service');
        $controller = $paymentService->loadPaymentHandler($payment);
        if ($controller === null || !method_exists($controller, 'getPaymentLink')) {
            return null;
        }

        $link = $controller->getPaymentLink($order);

        return $link !== null && $link !== '' ? (string) $link : null;
    }
}
