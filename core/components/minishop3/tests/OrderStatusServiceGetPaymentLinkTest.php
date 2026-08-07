<?php

/**
 * Regression: OrderStatusService::getPaymentLink must resolve payment handlers via PaymentService (#484).
 *
 * Run: php tests/OrderStatusServiceGetPaymentLinkTest.php
 */

declare(strict_types=1);

require __DIR__ . '/support/xpdo_stub.php';
require __DIR__ . '/support/xpdo_om_stub.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/StubMsOrder.php';
require __DIR__ . '/stubs/StubMsPayment.php';
require __DIR__ . '/stubs/StubPaymentLinkProvider.php';

use MiniShop3\MiniShop3;
use MiniShop3\Services\Order\OrderLogService;
use MiniShop3\Services\Order\OrderStatusService;
use MiniShop3\Services\Payment\PaymentService;
use MiniShop3\Tests\Stubs\StubMsOrder;
use MiniShop3\Tests\Stubs\StubMsPayment;
use MiniShop3\Tests\Stubs\StubPaymentLinkProvider;
use MODX\Revolution\modX;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function (mixed $expected, mixed $actual, string $label) use ($fail): void {
    if ($actual !== $expected) {
        $fail(sprintf(
            "%s:\nexpected: %s\nactual:   %s",
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
};

$invokeGetPaymentLink = static function (
    OrderStatusService $service,
    mixed $msPayment,
    StubMsOrder $msOrder
): string {
    $method = new ReflectionMethod(OrderStatusService::class, 'getPaymentLink');
    $method->setAccessible(true);

    return $method->invoke($service, $msPayment, $msOrder);
};

$modx = new class extends modX {
    public object $lexicon;

    public function __construct()
    {
        parent::__construct();
        $this->lexicon = new class {
            public function load(string ...$topics): void
            {
            }
        };
        $this->services = new class {
            /** @var array<string, object> */
            private array $services = [];

            public function register(string $key, object $service): void
            {
                $this->services[$key] = $service;
            }

            public function has(string $key): bool
            {
                return isset($this->services[$key]);
            }

            public function get(string $key): object
            {
                return $this->services[$key];
            }
        };
    }
};

$ms3 = new class($modx) extends MiniShop3 {
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }
};

$paymentService = new PaymentService($modx);
$ms3Property = new ReflectionProperty(PaymentService::class, 'ms3');
$ms3Property->setAccessible(true);
$ms3Property->setValue($paymentService, $ms3);

$modx->services->register('ms3', $ms3);
$modx->services->register('ms3_payment_service', $paymentService);

$orderLog = new OrderLogService($modx, $ms3);
$service = new OrderStatusService($modx, $ms3, $orderLog);

$msOrder = new StubMsOrder(['id' => 42]);

$link = $invokeGetPaymentLink(
    $service,
    new StubMsPayment(['class' => StubPaymentLinkProvider::class, 'id' => 1]),
    $msOrder
);
$assertSame(StubPaymentLinkProvider::PAYMENT_LINK, $link, 'payment link via PaymentService');

$emptyForScalar = $invokeGetPaymentLink($service, new stdClass(), $msOrder);
$assertSame('', $emptyForScalar, 'non-msPayment returns empty string');

$emptyForMissingClass = $invokeGetPaymentLink(
    $service,
    new StubMsPayment(['class' => 'MiniShop3\\Tests\\Stubs\\MissingPaymentProvider', 'id' => 2]),
    $msOrder
);
$assertSame('', $emptyForMissingClass, 'missing handler returns empty string');

$emptyForBlankClass = $invokeGetPaymentLink(
    $service,
    new StubMsPayment(['class' => '', 'id' => 3]),
    $msOrder
);
$assertSame('', $emptyForBlankClass, 'empty payment class returns empty string');

fwrite(STDOUT, "OK OrderStatusServiceGetPaymentLinkTest\n");
exit(0);
