<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Payment;

use MiniShop3\Controllers\Payment\PaymentProviderInterface;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MiniShop3\Services\Payment\PaymentLifecycleService;
use MiniShop3\Services\Payment\PaymentService;
use MiniShop3\Tests\Stubs\StubMsOrder;
use MiniShop3\Tests\Stubs\StubMsPayment;
use MiniShop3\Tests\Support\InMemoryPaymentAttemptStore;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class PaymentServiceSendAttemptTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 3) . '/stubs/StubMsOrder.php';
        require_once dirname(__DIR__, 3) . '/stubs/StubMsPayment.php';
    }

    public function testSuccessfulSendWithExternalIdInitiatesAttempt(): void
    {
        $store = new InMemoryPaymentAttemptStore();
        $lifecycle = new PaymentLifecycleService($store, new modX(), static fn (): bool => true);
        $service = new PaymentService($this->modxWithLifecycle($lifecycle));
        $order = new StubMsOrder(['id' => 31, 'cost' => 12.5, 'payment_id' => 9]);
        $payment = new StubMsPayment(['id' => 9, 'class' => 'AsyncPay']);

        $service->sendToPaymentGateway($payment, $this->sender([
            'success' => true,
            'data' => [
                'external_id' => 'gw-31',
                'payment_link' => 'https://pay.example/31',
            ],
        ]), $order);

        $row = $store->findByExternalId('AsyncPay', 'gw-31', 9);
        self::assertNotNull($row);
        self::assertSame('https://pay.example/31', $row['payload']['payment_link']);
        self::assertSame(31, $row['order_id']);
    }

    public function testSendWithoutExternalIdDoesNotInitiate(): void
    {
        $store = new InMemoryPaymentAttemptStore();
        $lifecycle = new PaymentLifecycleService($store, new modX(), static fn (): bool => true);
        $service = new PaymentService($this->modxWithLifecycle($lifecycle));
        $order = new StubMsOrder(['id' => 32, 'cost' => 10, 'payment_id' => 1]);
        $payment = new StubMsPayment(['id' => 1, 'class' => 'MiniShop3\\Controllers\\Payment\\DefaultPayment']);

        $service->sendToPaymentGateway($payment, $this->sender([
            'success' => true,
            'data' => [
                'payment_link' => 'https://shop.example/thanks',
                'order_id' => 32,
            ],
        ]), $order);

        self::assertNull($store->findLatestForOrder(32, 1));
    }

    /**
     * @param array<string, mixed> $response
     */
    private function sender(array $response): PaymentProviderInterface
    {
        return new class ($response) implements PaymentProviderInterface {
            /**
             * @param array<string, mixed> $response
             */
            public function __construct(private array $response)
            {
            }

            public function send(msOrder $order): array
            {
                return $this->response;
            }

            public function receive(msOrder $order): array
            {
                return [];
            }

            public function getPaymentLink(msOrder $order): ?string
            {
                return null;
            }

            public function getCost(msOrder $order, msPayment $payment, float $cost): float
            {
                return $cost;
            }

            public function getOrderHash(msOrder $order): string
            {
                return '';
            }
        };
    }

    private function modxWithLifecycle(PaymentLifecycleService $lifecycle): modX
    {
        return new class ($lifecycle) extends modX {
            public function __construct(private PaymentLifecycleService $lifecycle)
            {
                parent::__construct();
                $this->services = new class ($lifecycle) {
                    public function __construct(private PaymentLifecycleService $lifecycle)
                    {
                    }

                    public function has(string $key): bool
                    {
                        return $key === 'ms3_payment_lifecycle';
                    }

                    public function get(string $key): mixed
                    {
                        return $key === 'ms3_payment_lifecycle' ? $this->lifecycle : null;
                    }
                };
            }
        };
    }
}
