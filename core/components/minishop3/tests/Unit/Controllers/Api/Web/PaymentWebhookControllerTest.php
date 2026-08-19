<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Api\Web;

use MiniShop3\Controllers\Api\Web\PaymentWebhookController;
use MiniShop3\Controllers\Payment\DefaultPayment;
use MiniShop3\Controllers\Payment\PaymentProviderInterface;
use MiniShop3\Controllers\Payment\PaymentWebhookEvent;
use MiniShop3\Controllers\Payment\PaymentWebhookHandlerInterface;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Services\Payment\PaymentAttemptStatus;
use MiniShop3\Services\Payment\PaymentLifecycleService;
use MiniShop3\Tests\Stubs\StubMsOrder;
use MiniShop3\Tests\Stubs\StubMsPayment;
use MiniShop3\Tests\Support\InMemoryPaymentAttemptStore;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PaymentWebhookControllerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 4) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 4) . '/stubs/StubMsPayment.php';
        require_once dirname(__DIR__, 4) . '/stubs/StubMsOrder.php';
    }

    public function testMissingMethodIdIsBadRequest(): void
    {
        $controller = new PaymentWebhookController(new modX());
        $response = $controller->handle([]);
        $data = $response->getData();

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::BAD_REQUEST, $data['error_code'] ?? null);
    }

    public function testInvalidJsonIsBadRequest(): void
    {
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($this->handler(true, null)),
            '{not-json'
        );
        $response = $controller->handle(['payment_method_id' => 4]);
        $data = $response->getData();

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::BAD_REQUEST, $data['error_code'] ?? null);
    }

    public function testInvalidPayloadIsBadRequest(): void
    {
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($this->handler(true, null)),
            '{"event":"unknown"}'
        );
        $response = $controller->handle(['payment_method_id' => 4]);
        $data = $response->getData();

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::BAD_REQUEST, $data['error_code'] ?? null);
    }

    public function testBadSignatureIsUnauthorized(): void
    {
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($this->handler(false, null)),
            '{"event":"paid"}'
        );
        $response = $controller->handle(['payment_method_id' => 4]);
        $data = $response->getData();

        self::assertSame(HttpStatus::UNAUTHORIZED, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::UNAUTHORIZED, $data['error_code'] ?? null);
    }

    public function testVerifyWebhookReceivesRawBody(): void
    {
        $seenRaw = new stdClass();
        $seenRaw->value = null;
        $body = '{"event":"paid","id":"ext-4"}';
        $handler = $this->handler(false, null, $seenRaw);
        $controller = $this->controllerWithBody($this->modxWithHandler($handler), $body);
        $controller->handle(['payment_method_id' => 4]);

        self::assertSame($body, $seenRaw->value);
    }

    public function testPaidWebhookReturnsSuccess(): void
    {
        $seenRaw = new stdClass();
        $seenRaw->value = null;
        $event = new PaymentWebhookEvent(
            eventType: PaymentAttemptStatus::PAID,
            externalId: 'ext-4',
            amount: 25.0,
            providerEventId: 'cb-1',
        );
        $handler = $this->handler(true, $event, $seenRaw);
        $store = new InMemoryPaymentAttemptStore();
        $order = new StubMsOrder(['id' => 40, 'status_id' => 2, 'cost' => 25, 'payment_id' => 4]);
        $lifecycle = $this->lifecycle($store, $order);
        $lifecycle->initiate(40, 4, $handler::class, 25.0, 'RUB', 'ext-4');
        $body = '{"event":"paid","id":"ext-4"}';
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($handler, $lifecycle),
            $body
        );
        $response = $controller->handle(['payment_method_id' => 4]);
        $data = $response->getData();

        self::assertSame(HttpStatus::OK, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(PaymentAttemptStatus::PAID, $data['data']['status'] ?? null);
        self::assertSame($body, $seenRaw->value);
    }

    public function testUnexpectedLifecycleErrorIsInternal(): void
    {
        $handler = $this->handler(
            true,
            new PaymentWebhookEvent(eventType: PaymentAttemptStatus::PAID, externalId: 'x', amount: 1.0)
        );
        $fakeLifecycle = new class {
            public function applyWebhook(PaymentWebhookEvent $event, int $methodId, string $provider): array
            {
                throw new \RuntimeException('boom');
            }
        };
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($handler, $fakeLifecycle),
            '{"event":"paid"}'
        );
        $response = $controller->handle(['payment_method_id' => 4]);
        $data = $response->getData();

        self::assertSame(HttpStatus::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::INTERNAL_ERROR, $data['error_code'] ?? null);
    }

    public function testDefaultPaymentDoesNotImplementWebhookContract(): void
    {
        self::assertFalse(
            is_subclass_of(DefaultPayment::class, PaymentWebhookHandlerInterface::class)
        );
    }

    private function controllerWithBody(modX $modx, string $body): PaymentWebhookController
    {
        return new class ($modx, $body) extends PaymentWebhookController {
            public function __construct(modX $modx, private readonly string $raw)
            {
                parent::__construct($modx);
            }

            protected function readRawRequestBody(): string
            {
                return $this->raw;
            }
        };
    }

    private function lifecycle(InMemoryPaymentAttemptStore $store, msOrder $order): PaymentLifecycleService
    {
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
                if ($className === msOrder::class) {
                    return $this->order;
                }

                return null;
            }

            public function lexicon(string $key, array $params = []): string
            {
                return $key;
            }
        };

        return new PaymentLifecycleService($store, $modx, static fn (): bool => true);
    }

    private function modxWithHandler(object $handler, ?object $lifecycle = null): modX
    {
        $payment = new StubMsPayment(['id' => 4, 'active' => 1, 'class' => $handler::class]);

        return new class ($payment, $handler, $lifecycle) extends modX {
            public function __construct(
                private msPayment $payment,
                private object $handler,
                private ?object $lifecycle,
            ) {
                parent::__construct();
                $this->services = new class ($handler, $lifecycle) {
                    public function __construct(private object $handler, private ?object $lifecycle)
                    {
                    }

                    public function has(string $key): bool
                    {
                        return $key === 'ms3_payment_service'
                            || ($key === 'ms3_payment_lifecycle' && $this->lifecycle !== null);
                    }

                    public function get(string $key): mixed
                    {
                        if ($key === 'ms3_payment_lifecycle') {
                            return $this->lifecycle;
                        }
                        if ($key !== 'ms3_payment_service') {
                            return null;
                        }

                        return new class ($this->handler) {
                            public function __construct(private object $handler)
                            {
                            }

                            public function loadPaymentHandler(msPayment $payment): object
                            {
                                return $this->handler;
                            }
                        };
                    }
                };
            }

            public function getObject($className, $criteria = null)
            {
                return $className === msPayment::class ? $this->payment : null;
            }
        };
    }

    private function handler(bool $verified, ?PaymentWebhookEvent $event, ?stdClass $seenRaw = null): object
    {
        return new class ($verified, $event, $seenRaw) implements PaymentProviderInterface, PaymentWebhookHandlerInterface {
            public function __construct(
                private bool $verified,
                private ?PaymentWebhookEvent $event,
                private ?stdClass $seenRaw,
            ) {
            }

            public function verifyWebhook(string $rawBody, array $payload, array $headers, msPayment $method): bool
            {
                if ($this->seenRaw !== null) {
                    $this->seenRaw->value = $rawBody;
                }

                return $this->verified;
            }

            public function parseWebhook(array $payload, array $headers): ?PaymentWebhookEvent
            {
                return $this->event;
            }

            public function send(msOrder $order): array
            {
                return [];
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
}
