<?php

namespace MiniShop3\Services\Payment;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPayment;

/**
 * Resolves online payment URLs for order emails and storefront views.
 *
 * Eligible status IDs:
 * - notifications: system setting {@see ms3_payment_link_statuses} (CSV), fallback {@see ms3_status_new}
 * - ms3_get_order snippet: property `payStatus` (CSV), same parsing via {@see parseEligibleStatusIds()}
 */
final class PaymentLinkResolver
{
    /**
     * @param modX|object $modx MODX instance (plain object in smoke tests)
     */
    public function __construct(
        private object $modx,
        private ?object $paymentServiceOverride = null,
    ) {
    }

    /**
     * @return int[]
     */
    public static function parseEligibleStatusIds(string $csv): array
    {
        if ($csv === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $ids[] = (int) $part;
        }

        return array_values(array_unique($ids));
    }

    public static function isStatusEligibleForPaymentLink(
        int $statusId,
        bool $isFinal,
        int $paidStatusId,
        array $eligibleStatusIds
    ): bool {
        if ($isFinal || $statusId === $paidStatusId) {
            return false;
        }

        return in_array($statusId, $eligibleStatusIds, true);
    }

    public static function normalizePaymentLink(?string $link): ?string
    {
        return $link !== null && $link !== '' ? (string) $link : null;
    }

    /**
     * @return int[]
     */
    public function getEligibleStatusIds(): array
    {
        $csv = (string) $this->modx->getOption('ms3_payment_link_statuses', null, '');
        $ids = self::parseEligibleStatusIds($csv);
        if ($ids !== []) {
            return $ids;
        }

        return [(int) $this->modx->getOption('ms3_status_new', null, 2)];
    }

    public function shouldResolveForStatus(?msOrderStatus $status): bool
    {
        if ($status === null) {
            return false;
        }

        return self::isStatusEligibleForPaymentLink(
            (int) $status->get('id'),
            (bool) $status->get('final'),
            (int) $this->modx->getOption('ms3_status_paid', null, 3),
            $this->getEligibleStatusIds()
        );
    }

    /**
     * @param int[]|null $eligibleStatusIds Override eligible statuses (e.g. snippet payStatus property)
     */
    public function resolveForOrder(
        msOrder $order,
        ?msOrderStatus $status = null,
        ?array $eligibleStatusIds = null
    ): ?string {
        $eligibleStatusIds ??= $this->getEligibleStatusIds();
        $paidStatusId = (int) $this->modx->getOption('ms3_status_paid', null, 3);

        if ($status !== null) {
            if (!self::isStatusEligibleForPaymentLink(
                (int) $status->get('id'),
                (bool) $status->get('final'),
                $paidStatusId,
                $eligibleStatusIds
            )) {
                return null;
            }
        } elseif (!in_array((int) $order->get('status_id'), $eligibleStatusIds, true)) {
            return null;
        }

        $payment = $order->getOne('Payment');
        if (!$payment) {
            return null;
        }

        $controller = $this->loadPaymentHandler($payment);
        if ($controller === null || !method_exists($controller, 'getPaymentLink')) {
            return null;
        }

        return self::normalizePaymentLink($controller->getPaymentLink($order));
    }

    private function loadPaymentHandler(object $payment): ?object
    {
        if ($this->paymentServiceOverride !== null) {
            return $this->paymentServiceOverride->loadPaymentHandler($payment);
        }

        return $this->paymentService()->loadPaymentHandler($payment);
    }

    private function paymentService(): PaymentService
    {
        /** @var PaymentService $paymentService */
        $paymentService = $this->modx->services->get('ms3_payment_service');

        return $paymentService;
    }
}
