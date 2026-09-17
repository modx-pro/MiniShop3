<?php

declare(strict_types=1);

namespace MiniShop3\Services\Payment;

use MiniShop3\Controllers\Payment\PaymentWebhookEvent;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Order\OrderStatusChanger;
use MODX\Revolution\modX;

/**
 * Coordinates payment attempt state and order status. Providers must not write status_id.
 *
 * @phpstan-import-type PaymentAttemptRow from PaymentAttemptStoreInterface
 */
class PaymentLifecycleService
{
    private const ALLOWED_TRANSITIONS = [
        PaymentAttemptStatus::PENDING => [
            PaymentAttemptStatus::PENDING,
            PaymentAttemptStatus::AUTHORIZED,
            PaymentAttemptStatus::PAID,
            PaymentAttemptStatus::FAILED,
            PaymentAttemptStatus::CANCELLED,
        ],
        PaymentAttemptStatus::AUTHORIZED => [
            PaymentAttemptStatus::AUTHORIZED,
            PaymentAttemptStatus::PAID,
            PaymentAttemptStatus::FAILED,
            PaymentAttemptStatus::CANCELLED,
        ],
        PaymentAttemptStatus::PAID => [
            PaymentAttemptStatus::PAID,
            PaymentAttemptStatus::REFUNDED,
            PaymentAttemptStatus::PARTIALLY_REFUNDED,
        ],
        PaymentAttemptStatus::PARTIALLY_REFUNDED => [
            PaymentAttemptStatus::PARTIALLY_REFUNDED,
            PaymentAttemptStatus::REFUNDED,
        ],
        PaymentAttemptStatus::FAILED => [PaymentAttemptStatus::FAILED],
        PaymentAttemptStatus::CANCELLED => [PaymentAttemptStatus::CANCELLED],
        PaymentAttemptStatus::REFUNDED => [PaymentAttemptStatus::REFUNDED],
    ];

    private const BLOCKED_PAYLOAD_KEYS = [
        'password',
        'secret',
        'token',
        'api_key',
        'secret_key',
        'properties',
        'class',
        'authorization',
    ];

    /**
     * @param OrderStatusChanger $orderStatus
     */
    public function __construct(
        private readonly PaymentAttemptStoreInterface $store,
        private readonly modX $modx,
        private readonly OrderStatusChanger $orderStatus,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return PaymentAttemptRow
     */
    public function initiate(
        int $orderId,
        int $paymentMethodId,
        string $provider,
        float $amount,
        string $currency = 'RUB',
        ?string $externalId = null,
        array $payload = [],
    ): array {
        $payload = $this->sanitizePayload($payload);
        if ($externalId !== null && $externalId !== '') {
            $existing = $this->store->findByExternalId($provider, $externalId, $paymentMethodId);
            if ($existing !== null) {
                return $this->store->update($existing['id'], [
                    'payload' => array_merge($existing['payload'], $payload),
                ]);
            }
        }
        $open = $this->store->findLatestForOrder($orderId, $paymentMethodId);
        if ($open !== null && $this->isOpen($open['status']) && $this->canRebindOpenAttempt($open, $externalId)) {
            return $this->store->update($open['id'], [
                'external_id' => $externalId ?? $open['external_id'],
                'amount' => $amount,
                'currency' => $currency,
                'payload' => array_merge($open['payload'], $payload),
            ]);
        }

        return $this->store->create(
            $orderId,
            $paymentMethodId,
            $provider,
            $externalId,
            PaymentAttemptStatus::PENDING,
            $amount,
            $currency,
            $payload
        );
    }

    /**
     * @return PaymentAttemptRow
     */
    public function markPending(int $attemptId, ?string $providerEventId = null): array
    {
        return $this->apply($attemptId, PaymentAttemptStatus::PENDING, $providerEventId);
    }

    /**
     * @return PaymentAttemptRow
     */
    public function markAuthorized(int $attemptId, ?string $providerEventId = null): array
    {
        return $this->apply($attemptId, PaymentAttemptStatus::AUTHORIZED, $providerEventId);
    }

    /**
     * @return PaymentAttemptRow
     */
    public function markPaid(int $attemptId, ?string $providerEventId = null, ?float $paidAmount = null): array
    {
        return $this->apply($attemptId, PaymentAttemptStatus::PAID, $providerEventId, $paidAmount);
    }

    /**
     * @return PaymentAttemptRow
     */
    public function markFailed(int $attemptId, ?string $providerEventId = null): array
    {
        return $this->apply($attemptId, PaymentAttemptStatus::FAILED, $providerEventId);
    }

    /**
     * @return PaymentAttemptRow
     */
    public function markCancelled(int $attemptId, ?string $providerEventId = null): array
    {
        return $this->apply($attemptId, PaymentAttemptStatus::CANCELLED, $providerEventId);
    }

    /**
     * @param array<string, mixed> $extraFields
     * @return PaymentAttemptRow
     */
    public function refund(
        int $attemptId,
        float $amount,
        ?string $refundExternalId = null,
        ?string $providerEventId = null,
        array $extraFields = [],
    ): array {
        $attempt = $this->requireAttempt($attemptId);
        $amount = round($amount, 3);
        if ($amount <= 0) {
            throw new PaymentLifecycleException('ms3_err_payment_webhook_invalid', ['qty' => $amount]);
        }
        $eventKey = $this->refundEventKey($providerEventId, $refundExternalId);
        foreach ([PaymentAttemptStatus::PARTIALLY_REFUNDED, PaymentAttemptStatus::REFUNDED] as $recordedType) {
            if ($this->store->hasEvent($attemptId, $recordedType, $eventKey)) {
                $this->syncOrderStatus($attempt['order_id'], $attempt['status']);

                return $attempt;
            }
        }
        $refundedAmount = round($attempt['refunded_amount'] + $amount, 3);
        if ($refundedAmount > $attempt['amount'] + 0.0005) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_webhook_invalid',
                ['qty' => $refundedAmount, 'amount' => $attempt['amount']]
            );
        }
        $target = $refundedAmount < $attempt['amount']
            ? PaymentAttemptStatus::PARTIALLY_REFUNDED
            : PaymentAttemptStatus::REFUNDED;
        $this->assertTransition($attempt['status'], $target);
        $fields = array_merge($extraFields, [
            'status' => $target,
            'refunded_amount' => $refundedAmount,
            'refund_external_id' => $refundExternalId,
            'refundedon' => time(),
        ]);

        return $this->commit($attempt, $target, $eventKey, $fields);
    }

    public function storedPaymentLink(int $orderId, ?int $paymentMethodId = null): ?string
    {
        $attempt = $this->store->findLatestForOrder($orderId, $paymentMethodId);
        if ($attempt === null || !$this->isOpen($attempt['status'])) {
            return null;
        }
        $link = $attempt['payload']['payment_link'] ?? null;

        return PaymentLinkResolver::normalizePaymentLink(is_string($link) ? $link : null);
    }

    /**
     * @return PaymentAttemptRow
     */
    public function applyWebhook(PaymentWebhookEvent $event, int $paymentMethodId, string $provider): array
    {
        $this->assertFinancialExternalId($event);
        $attempt = $this->resolveAttempt($event, $paymentMethodId, $provider);
        $this->assertWebhookCurrency($attempt, $event);
        $payloadFields = [];
        if ($event->payload !== []) {
            $payloadFields['payload'] = array_merge(
                $attempt['payload'],
                $this->sanitizePayload($event->payload)
            );
        }
        if (
            $event->externalId !== null
            && $event->externalId !== ''
            && ($attempt['external_id'] === null || $attempt['external_id'] === '')
        ) {
            $payloadFields['external_id'] = $event->externalId;
        }
        $eventId = $event->providerEventId;

        return match ($event->eventType) {
            PaymentAttemptStatus::PENDING,
            PaymentAttemptStatus::AUTHORIZED,
            PaymentAttemptStatus::FAILED,
            PaymentAttemptStatus::CANCELLED => $this->apply(
                $attempt['id'],
                $event->eventType,
                $eventId,
                null,
                $payloadFields
            ),
            PaymentAttemptStatus::PAID => $this->apply(
                $attempt['id'],
                $event->eventType,
                $eventId,
                $this->requirePaidAmount($event),
                $payloadFields
            ),
            PaymentAttemptStatus::REFUNDED => $this->refundWebhook($attempt, $event, $eventId, $payloadFields),
            PaymentAttemptStatus::PARTIALLY_REFUNDED => $this->refund(
                $attempt['id'],
                $event->refundAmount ?? 0.0,
                $event->refundExternalId,
                $eventId,
                $payloadFields
            ),
            default => throw new PaymentLifecycleException(
                'ms3_err_payment_webhook_invalid',
                ['event' => $event->eventType]
            ),
        };
    }

    /**
     * @param array<string, mixed> $fields
     * @return PaymentAttemptRow
     */
    private function refundWebhook(
        array $attempt,
        PaymentWebhookEvent $event,
        ?string $eventId,
        array $fields = [],
    ): array {
        $qty = $event->refundAmount ?? $this->remainingRefundable($attempt);
        if ($qty <= 0) {
            if ($attempt['status'] === PaymentAttemptStatus::REFUNDED) {
                $this->syncOrderStatus($attempt['order_id'], $attempt['status']);

                return $attempt;
            }
            throw new PaymentLifecycleException('ms3_err_payment_webhook_invalid', ['qty' => $qty]);
        }

        return $this->refund($attempt['id'], $qty, $event->refundExternalId, $eventId, $fields);
    }

    /**
     * @param array<string, mixed> $fields
     * @return PaymentAttemptRow
     */
    private function apply(
        int $attemptId,
        string $target,
        ?string $providerEventId,
        ?float $paidAmount = null,
        array $fields = [],
    ): array {
        $attempt = $this->requireAttempt($attemptId);
        $eventKey = $this->applyEventKey($target, $providerEventId);
        if ($target === PaymentAttemptStatus::PAID) {
            $this->assertPaidPreconditions($attempt, $paidAmount);
        }

        return $this->commit($attempt, $target, $eventKey, $fields);
    }

    /**
     * Persist fields+event atomically, then heal the order status.
     *
     * @param PaymentAttemptRow $attempt
     * @param array<string, mixed> $fields
     * @return PaymentAttemptRow
     */
    private function commit(array $attempt, string $target, string $eventKey, array $fields = []): array
    {
        if ($attempt['status'] !== $target) {
            $this->assertTransition($attempt['status'], $target);
            $fields['status'] = $target;
        }
        $updated = $this->store->writeWithEvent($attempt['id'], $target, $eventKey, $fields);
        $this->syncOrderStatus($updated['order_id'], $target);

        return $updated;
    }

    /**
     * @return PaymentAttemptRow
     */
    private function resolveAttempt(
        PaymentWebhookEvent $event,
        int $paymentMethodId,
        string $provider,
    ): array {
        if ($event->externalId !== null && $event->externalId !== '') {
            $byExternal = $this->store->findByExternalId($provider, $event->externalId, $paymentMethodId);
            if ($byExternal !== null) {
                $this->assertAttemptMatchesWebhook($byExternal, $paymentMethodId);

                return $byExternal;
            }
            $otherMethod = $this->store->findByExternalId($provider, $event->externalId);
            if ($otherMethod !== null) {
                throw new PaymentLifecycleException(
                    'ms3_err_payment_event_conflict',
                    ['from' => 'payment_method', 'to' => (string) $paymentMethodId],
                    PaymentLifecycleException::KIND_CONFLICT
                );
            }
            $order = $this->resolveOrder($event);
            if ($order !== null) {
                $this->assertOrderPaymentMethod($order, $paymentMethodId);
                $existing = $this->store->findLatestForOrder((int) $order->get('id'), $paymentMethodId);
                if (
                    $existing !== null
                    && ($existing['external_id'] === null || $existing['external_id'] === '')
                ) {
                    return $existing;
                }
            }

            throw new PaymentLifecycleException(
                'ms3_err_payment_attempt_nf',
                ['external_id' => $event->externalId],
                PaymentLifecycleException::KIND_NOT_FOUND
            );
        }
        $order = $this->resolveOrder($event);
        if ($order === null) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_attempt_nf',
                [],
                PaymentLifecycleException::KIND_NOT_FOUND
            );
        }
        $this->assertOrderPaymentMethod($order, $paymentMethodId);
        $existing = $this->store->findLatestForOrder((int) $order->get('id'), $paymentMethodId);
        if ($existing === null) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_attempt_nf',
                ['order_id' => (int) $order->get('id')],
                PaymentLifecycleException::KIND_NOT_FOUND
            );
        }

        return $existing;
    }

    private function resolveOrder(PaymentWebhookEvent $event): ?msOrder
    {
        if ($event->orderId !== null && $event->orderId > 0) {
            $order = $this->modx->getObject(msOrder::class, ['id' => $event->orderId]);
            if ($order instanceof msOrder) {
                return $order;
            }
        }
        if ($event->orderUuid !== null && $event->orderUuid !== '') {
            $order = $this->modx->getObject(msOrder::class, ['uuid' => $event->orderUuid]);
            if ($order instanceof msOrder) {
                return $order;
            }
        }

        return null;
    }

    /**
     * @return PaymentAttemptRow
     */
    private function requireAttempt(int $attemptId): array
    {
        $attempt = $this->store->findById($attemptId);
        if ($attempt === null) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_attempt_nf',
                ['id' => $attemptId],
                PaymentLifecycleException::KIND_NOT_FOUND
            );
        }

        return $attempt;
    }

    private function assertTransition(string $from, string $to): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_event_conflict',
                ['from' => $from, 'to' => $to],
                PaymentLifecycleException::KIND_CONFLICT
            );
        }
    }

    /**
     * @param PaymentAttemptRow $attempt
     */
    private function assertPaidPreconditions(array $attempt, ?float $paidAmount): void
    {
        $order = $this->modx->getObject(msOrder::class, ['id' => $attempt['order_id']]);
        if (!$order instanceof msOrder) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_attempt_nf',
                ['order_id' => $attempt['order_id']],
                PaymentLifecycleException::KIND_NOT_FOUND
            );
        }
        $canceledId = (int) $this->modx->getOption('ms3_status_canceled', null, 5) ?: 5;
        if ((int) $order->get('status_id') === $canceledId) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_event_conflict',
                ['from' => 'canceled', 'to' => PaymentAttemptStatus::PAID],
                PaymentLifecycleException::KIND_CONFLICT
            );
        }
        $this->assertOrderPaymentMethod($order, $attempt['payment_method_id']);
        $orderCost = (float) $order->get('cost');
        if (abs($attempt['amount'] - $orderCost) > 0.001) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_event_conflict',
                ['amount' => $attempt['amount'], 'cost' => $orderCost],
                PaymentLifecycleException::KIND_CONFLICT
            );
        }
        if ($paidAmount === null) {
            return;
        }
        if (abs($paidAmount - $attempt['amount']) > 0.001) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_event_conflict',
                ['amount' => $paidAmount],
                PaymentLifecycleException::KIND_CONFLICT
            );
        }
    }

    /**
     * @param PaymentAttemptRow $attempt
     */
    private function assertAttemptMatchesWebhook(array $attempt, int $paymentMethodId): void
    {
        if ($attempt['payment_method_id'] !== $paymentMethodId) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_event_conflict',
                ['from' => 'payment_method', 'to' => (string) $paymentMethodId],
                PaymentLifecycleException::KIND_CONFLICT
            );
        }
        $order = $this->modx->getObject(msOrder::class, ['id' => $attempt['order_id']]);
        if (!$order instanceof msOrder) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_attempt_nf',
                ['order_id' => $attempt['order_id']],
                PaymentLifecycleException::KIND_NOT_FOUND
            );
        }
        $this->assertOrderPaymentMethod($order, $paymentMethodId);
    }

    private function assertOrderPaymentMethod(msOrder $order, int $paymentMethodId): void
    {
        $orderPaymentId = (int) $order->get('payment_id');
        if ($orderPaymentId > 0 && $orderPaymentId !== $paymentMethodId) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_event_conflict',
                ['from' => (string) $orderPaymentId, 'to' => (string) $paymentMethodId],
                PaymentLifecycleException::KIND_CONFLICT
            );
        }
    }

    private function applyEventKey(string $target, ?string $providerEventId): string
    {
        if ($providerEventId !== null && $providerEventId !== '') {
            return $providerEventId;
        }

        return $target;
    }

    private function refundEventKey(?string $providerEventId, ?string $refundExternalId): string
    {
        $key = $providerEventId ?? $refundExternalId ?? '';
        if ($key === '') {
            throw new PaymentLifecycleException('ms3_err_payment_webhook_invalid', ['event' => 'refund']);
        }

        return $key;
    }

    private function syncOrderStatus(int $orderId, string $attemptStatus): void
    {
        $statusId = $this->orderStatusFor($attemptStatus);
        if ($statusId <= 0) {
            return;
        }
        $result = $this->orderStatus->ensure($orderId, $statusId);
        if ($result === true) {
            return;
        }
        $message = is_string($result) ? $result : 'ms3_err_unknown';
        throw new PaymentLifecycleException(
            'ms3_err_payment_event_conflict',
            ['status' => $message],
            PaymentLifecycleException::KIND_CONFLICT,
            $message
        );
    }

    private function orderStatusFor(string $attemptStatus): int
    {
        return match ($attemptStatus) {
            PaymentAttemptStatus::PAID => (int) $this->modx->getOption('ms3_status_paid', null, 3) ?: 3,
            PaymentAttemptStatus::FAILED, PaymentAttemptStatus::CANCELLED => (int) $this->modx->getOption(
                'ms3_payment_on_failed_status',
                null,
                5
            ),
            PaymentAttemptStatus::REFUNDED => (int) $this->modx->getOption('ms3_payment_on_refunded_status', null, 5),
            default => 0,
        };
    }

    private function isOpen(string $status): bool
    {
        return in_array($status, [
            PaymentAttemptStatus::PENDING,
            PaymentAttemptStatus::AUTHORIZED,
        ], true);
    }

    /**
     * @param PaymentAttemptRow $open
     */
    private function canRebindOpenAttempt(array $open, ?string $externalId): bool
    {
        $current = $open['external_id'] ?? '';
        if ($current === '') {
            return true;
        }
        if ($externalId === null || $externalId === '') {
            return true;
        }

        return $current === $externalId;
    }

    private function assertFinancialExternalId(PaymentWebhookEvent $event): void
    {
        if (!in_array($event->eventType, [
            PaymentAttemptStatus::PAID,
            PaymentAttemptStatus::REFUNDED,
            PaymentAttemptStatus::PARTIALLY_REFUNDED,
        ], true)) {
            return;
        }
        if ($event->externalId !== null && $event->externalId !== '') {
            return;
        }

        throw new PaymentLifecycleException(
            'ms3_err_payment_webhook_invalid',
            ['external_id' => 'required'],
            PaymentLifecycleException::KIND_INVALID
        );
    }

    /**
     * @param PaymentAttemptRow $attempt
     */
    private function assertWebhookCurrency(array $attempt, PaymentWebhookEvent $event): void
    {
        if ($event->currency === null || $event->currency === '') {
            return;
        }
        if (strcasecmp($event->currency, $attempt['currency']) === 0) {
            return;
        }

        throw new PaymentLifecycleException(
            'ms3_err_payment_event_conflict',
            ['from' => $attempt['currency'], 'to' => $event->currency],
            PaymentLifecycleException::KIND_CONFLICT
        );
    }

    private function requirePaidAmount(PaymentWebhookEvent $event): float
    {
        if ($event->amount === null) {
            throw new PaymentLifecycleException(
                'ms3_err_payment_webhook_invalid',
                ['amount' => 'required']
            );
        }

        return $event->amount;
    }

    /**
     * @param PaymentAttemptRow $attempt
     */
    private function remainingRefundable(array $attempt): float
    {
        return round(max(0.0, $attempt['amount'] - $attempt['refunded_amount']), 3);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key) || in_array(strtolower($key), self::BLOCKED_PAYLOAD_KEYS, true)) {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = $this->sanitizePayload($value);
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
