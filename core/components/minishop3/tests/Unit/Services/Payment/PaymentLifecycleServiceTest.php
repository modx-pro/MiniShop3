<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Payment;

use MiniShop3\Controllers\Payment\PaymentWebhookEvent;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Payment\PaymentAttemptStatus;
use MiniShop3\Services\Payment\PaymentLifecycleException;
use MiniShop3\Services\Payment\PaymentLifecycleService;
use MiniShop3\Tests\Support\InMemoryPaymentAttemptStore;
use MiniShop3\Tests\Stubs\StubMsOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class PaymentLifecycleServiceTest extends TestCase
{
    /** @var list<array{0: int, 1: int}> */
    private array $statusChanges = [];

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 3) . '/stubs/StubMsOrder.php';
        $this->statusChanges = [];
    }

    public function testPaidCallbackSetsOrderPaidAndIsIdempotent(): void
    {
        $service = $this->service();
        $attempt = $service->initiate(10, 2, 'TestPay', 100.0, 'RUB', 'ext-1', ['payment_link' => 'https://pay.example/1']);
        self::assertSame('https://pay.example/1', $service->storedPaymentLink(10, 2));
        self::assertNull($service->storedPaymentLink(10, 99));

        $paid = $service->markPaid($attempt['id'], 'evt-paid');
        self::assertSame(PaymentAttemptStatus::PAID, $paid['status']);
        self::assertSame([[10, 3]], $this->statusChanges);

        $again = $service->markPaid($attempt['id'], 'evt-paid');
        self::assertSame(PaymentAttemptStatus::PAID, $again['status']);
        self::assertCount(2, $this->statusChanges);
        self::assertNull($service->storedPaymentLink(10, 2));
    }

    public function testFailedPaymentCancelsOrder(): void
    {
        $service = $this->service();
        $attempt = $service->initiate(11, 2, 'TestPay', 50.0);
        $failed = $service->markFailed($attempt['id'], 'evt-fail');
        self::assertSame(PaymentAttemptStatus::FAILED, $failed['status']);
        self::assertSame([[11, 5]], $this->statusChanges);
    }

    public function testPaidAfterCanceledOrderConflicts(): void
    {
        $order = new StubMsOrder(['id' => 12, 'status_id' => 5, 'cost' => 10]);
        $service = $this->service($order);
        $attempt = $service->initiate(12, 2, 'TestPay', 10.0);
        $this->expectException(PaymentLifecycleException::class);
        $service->markPaid($attempt['id'], 'evt-late');
    }

    public function testRefundAndPartialRefundPersist(): void
    {
        $service = $this->service();
        $attempt = $service->initiate(10, 2, 'TestPay', 100.0);
        $service->markPaid($attempt['id'], 'paid');
        $this->statusChanges = [];

        $partial = $service->partialRefund($attempt['id'], 30.0, 'ref-1', 'evt-r1');
        self::assertSame(PaymentAttemptStatus::PARTIALLY_REFUNDED, $partial['status']);
        self::assertSame(30.0, $partial['refunded_amount']);
        self::assertSame([], $this->statusChanges);

        $full = $service->refund($attempt['id'], 70.0, 'ref-2', 'evt-r2');
        self::assertSame(PaymentAttemptStatus::REFUNDED, $full['status']);
        self::assertSame(100.0, $full['refunded_amount']);
        self::assertSame([[10, 5]], $this->statusChanges);

        $again = $service->refund($attempt['id'], 70.0, 'ref-2', 'evt-r2');
        self::assertSame(PaymentAttemptStatus::REFUNDED, $again['status']);
        self::assertCount(2, $this->statusChanges);
    }

    public function testRefundWithoutEventIdIsInvalid(): void
    {
        $service = $this->service();
        $attempt = $service->initiate(10, 2, 'TestPay', 100.0);
        $service->markPaid($attempt['id'], 'paid');
        try {
            $service->refund($attempt['id'], 10.0);
            self::fail('expected invalid refund without event id');
        } catch (PaymentLifecycleException $exception) {
            self::assertSame(PaymentLifecycleException::KIND_INVALID, $exception->getKind());
        }
        try {
            $service->refund($attempt['id'], 10.0);
            self::fail('expected second refund without event id to stay invalid');
        } catch (PaymentLifecycleException $exception) {
            self::assertSame(PaymentLifecycleException::KIND_INVALID, $exception->getKind());
        }
    }

    public function testOverRefundIsInvalid(): void
    {
        $service = $this->service();
        $attempt = $service->initiate(10, 2, 'TestPay', 100.0);
        $service->markPaid($attempt['id'], 'paid');
        $service->partialRefund($attempt['id'], 80.0, 'ref-1', 'evt-r1');
        $this->expectException(PaymentLifecycleException::class);
        $service->refund($attempt['id'], 30.0, 'ref-2', 'evt-r2');
    }

    public function testRetryAfterFailedOrderSyncReplaysStatusChange(): void
    {
        $calls = 0;
        $service = $this->service(changeStatus: function (int $orderId, int $statusId) use (&$calls): bool|string {
            $this->statusChanges[] = [$orderId, $statusId];
            $calls++;

            return $calls === 1 ? 'ms3_err_unknown' : true;
        });
        $attempt = $service->initiate(10, 2, 'TestPay', 100.0);
        try {
            $service->markPaid($attempt['id'], 'evt-paid');
            self::fail('expected first sync to fail');
        } catch (PaymentLifecycleException $exception) {
            self::assertSame(PaymentLifecycleException::KIND_CONFLICT, $exception->getKind());
        }
        $paid = $service->markPaid($attempt['id'], 'evt-paid');
        self::assertSame(PaymentAttemptStatus::PAID, $paid['status']);
        self::assertSame(2, $calls);
    }

    public function testWebhookDoesNotCreateAttempt(): void
    {
        $service = $this->service();
        try {
            $service->applyWebhook(
                new PaymentWebhookEvent(eventType: PaymentAttemptStatus::PAID, externalId: 'missing'),
                2,
                'TestPay'
            );
            self::fail('expected missing attempt');
        } catch (PaymentLifecycleException $exception) {
            self::assertSame(PaymentLifecycleException::KIND_NOT_FOUND, $exception->getKind());
        }
    }

    public function testWebhookWrongPaymentMethodConflicts(): void
    {
        $order = new StubMsOrder(['id' => 14, 'status_id' => 2, 'cost' => 20, 'payment_id' => 7, 'uuid' => 'u-14']);
        $service = $this->service($order);
        $service->initiate(14, 7, 'TestPay', 20.0, 'RUB', 'gw-14');
        try {
            $service->applyWebhook(
                new PaymentWebhookEvent(
                    eventType: PaymentAttemptStatus::PAID,
                    externalId: 'gw-14',
                    providerEventId: 'cb-1',
                ),
                9,
                'TestPay'
            );
            self::fail('expected method conflict');
        } catch (PaymentLifecycleException $exception) {
            self::assertSame(PaymentLifecycleException::KIND_CONFLICT, $exception->getKind());
        }
    }

    public function testPaidAmountMismatchConflicts(): void
    {
        $service = $this->service();
        $attempt = $service->initiate(10, 2, 'TestPay', 100.0);
        $this->expectException(PaymentLifecycleException::class);
        $service->markPaid($attempt['id'], 'evt-paid', 40.0);
    }

    public function testPaidOnMissingOrderIsNotFound(): void
    {
        $service = $this->service();
        $attempt = $service->initiate(99, 2, 'TestPay', 1.0);
        try {
            $service->markPaid($attempt['id'], 'evt-paid');
            self::fail('expected missing order');
        } catch (PaymentLifecycleException $exception) {
            self::assertSame(PaymentLifecycleException::KIND_NOT_FOUND, $exception->getKind());
        }
    }

    public function testWebhookResolvesByExternalId(): void
    {
        $service = $this->service(new StubMsOrder(['id' => 14, 'status_id' => 2, 'cost' => 20, 'uuid' => 'u-14']));
        $service->initiate(14, 7, 'TestPay', 20.0, 'RUB', 'gw-14');
        $event = new PaymentWebhookEvent(
            eventType: PaymentAttemptStatus::PAID,
            externalId: 'gw-14',
            amount: 20.0,
            providerEventId: 'cb-1',
        );
        $paid = $service->applyWebhook($event, 7, 'TestPay');
        self::assertSame(PaymentAttemptStatus::PAID, $paid['status']);
        self::assertSame(14, $paid['order_id']);
    }

    public function testUnknownWebhookEventIsInvalid(): void
    {
        $service = $this->service();
        $service->initiate(16, 2, 'TestPay', 1.0, 'RUB', 'gw-16');
        $this->expectException(PaymentLifecycleException::class);
        $service->applyWebhook(
            new PaymentWebhookEvent(eventType: 'chargeback', externalId: 'gw-16'),
            2,
            'TestPay'
        );
    }

    public function testSecretsAreStrippedFromPayload(): void
    {
        $service = $this->service();
        $attempt = $service->initiate(15, 2, 'TestPay', 1.0, 'RUB', null, [
            'payment_link' => 'https://pay.example/x',
            'secret' => 'nope',
            'properties' => ['token' => 'x'],
            'meta' => ['secret' => 'nested', 'ok' => 'yes'],
        ]);
        self::assertSame('https://pay.example/x', $attempt['payload']['payment_link']);
        self::assertArrayNotHasKey('secret', $attempt['payload']);
        self::assertArrayNotHasKey('properties', $attempt['payload']);
        self::assertSame(['ok' => 'yes'], $attempt['payload']['meta']);
    }

    public function testUnknownExternalIdDoesNotPayLatestAttempt(): void
    {
        $order = new StubMsOrder(['id' => 10, 'status_id' => 2, 'cost' => 100, 'payment_id' => 2]);
        $service = $this->service($order);
        $current = $service->initiate(10, 2, 'TestPay', 100.0, 'RUB', 'BBB', [
            'payment_link' => 'https://pay.example/b',
        ]);
        try {
            $service->applyWebhook(
                new PaymentWebhookEvent(
                    eventType: PaymentAttemptStatus::PAID,
                    externalId: 'AAA',
                    orderId: 10,
                    amount: 100.0,
                    providerEventId: 'old-cb',
                ),
                2,
                'TestPay'
            );
            self::fail('stale AAA must not pay BBB');
        } catch (PaymentLifecycleException $exception) {
            self::assertSame(PaymentLifecycleException::KIND_NOT_FOUND, $exception->getKind());
        }
        self::assertSame('https://pay.example/b', $service->storedPaymentLink(10, 2));
        self::assertSame('BBB', $current['external_id']);
    }

    public function testInitiateDoesNotOverwriteDifferentExternalId(): void
    {
        $service = $this->service();
        $first = $service->initiate(10, 2, 'TestPay', 100.0, 'RUB', 'AAA');
        $second = $service->initiate(10, 2, 'TestPay', 100.0, 'RUB', 'BBB');
        self::assertNotSame($first['id'], $second['id']);
        self::assertSame('AAA', $first['external_id']);
        self::assertSame('BBB', $second['external_id']);
    }

    public function testStoredLinkIgnoresFailedAttempt(): void
    {
        $service = $this->service();
        $attempt = $service->initiate(10, 2, 'TestPay', 100.0, 'RUB', 'ext-fail', [
            'payment_link' => 'https://pay.example/stale',
        ]);
        $service->markFailed($attempt['id'], 'evt-fail');
        self::assertNull($service->storedPaymentLink(10, 2));
    }

    public function testWebhookPaidWithoutAmountIsInvalid(): void
    {
        $service = $this->service();
        $service->initiate(10, 2, 'TestPay', 100.0, 'RUB', 'ext-amt');
        try {
            $service->applyWebhook(
                new PaymentWebhookEvent(
                    eventType: PaymentAttemptStatus::PAID,
                    externalId: 'ext-amt',
                    providerEventId: 'cb-amt',
                ),
                2,
                'TestPay'
            );
            self::fail('paid webhook must include amount');
        } catch (PaymentLifecycleException $exception) {
            self::assertSame(PaymentLifecycleException::KIND_INVALID, $exception->getKind());
        }
    }

    public function testFullRefundWebhookWithoutAmountUsesRemainder(): void
    {
        $service = $this->service();
        $attempt = $service->initiate(10, 2, 'TestPay', 100.0, 'RUB', 'ext-ref');
        $service->markPaid($attempt['id'], 'paid');
        $service->partialRefund($attempt['id'], 30.0, 'ref-1', 'evt-r1');
        $this->statusChanges = [];
        $full = $service->applyWebhook(
            new PaymentWebhookEvent(
                eventType: PaymentAttemptStatus::REFUNDED,
                externalId: 'ext-ref',
                providerEventId: 'evt-r2',
                refundExternalId: 'ref-2',
            ),
            2,
            'TestPay'
        );
        self::assertSame(PaymentAttemptStatus::REFUNDED, $full['status']);
        self::assertSame(100.0, $full['refunded_amount']);
        self::assertSame([[10, 5]], $this->statusChanges);
    }

    /**
     * @param \Closure(int, int): (bool|string)|null $changeStatus
     */
    private function service(?msOrder $order = null, ?\Closure $changeStatus = null): PaymentLifecycleService
    {
        $order ??= new StubMsOrder(['id' => 10, 'status_id' => 2, 'cost' => 100]);
        $store = new InMemoryPaymentAttemptStore();
        $modx = new class ($order) extends modX {
            public function __construct(private msOrder $order)
            {
                parent::__construct();
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return match ($key) {
                    'ms3_status_paid' => 3,
                    'ms3_status_canceled' => 5,
                    'ms3_payment_on_failed_status' => 5,
                    'ms3_payment_on_refunded_status' => 5,
                    default => $default,
                };
            }

            public function getObject($className, $criteria = null)
            {
                if ($className !== msOrder::class) {
                    return null;
                }
                if (is_array($criteria) && isset($criteria['id']) && (int) $criteria['id'] === (int) $this->order->get('id')) {
                    return $this->order;
                }
                if (is_array($criteria) && isset($criteria['uuid']) && $criteria['uuid'] === $this->order->get('uuid')) {
                    return $this->order;
                }

                return null;
            }

            public function lexicon(string $key, array $params = []): string
            {
                return $key;
            }
        };

        return new PaymentLifecycleService(
            $store,
            $modx,
            $changeStatus ?? function (int $orderId, int $statusId): bool|string {
                $this->statusChanges[] = [$orderId, $statusId];

                return true;
            }
        );
    }
}
