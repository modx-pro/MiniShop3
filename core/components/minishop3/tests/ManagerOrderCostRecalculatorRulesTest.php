<?php

/**
 * Regression smoke tests for persisted order cost rules (#373).
 *
 * 1. {@see OrderPersistedCostRules} — pure formulas.
 * 2. {@see ManagerOrderCostRecalculator::calculateBreakdown()} — production path with stubs.
 *
 * Run: php tests/ManagerOrderCostRecalculatorRulesTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/XpdoStub.php';
require __DIR__ . '/stubs/OrderCostRecalculatorModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msPayment;
use MiniShop3\Services\Order\ManagerOrderCostRecalculator;
use MiniShop3\Services\Order\OrderPersistedCostRules;
use MiniShop3\Utils\Utils;
use MODX\Revolution\OrderCostRecalculatorModxStub;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertTrue = static function (bool $value, string $case) use ($fail): void {
    if (!$value) {
        $fail($case);
    }
};

// --- OrderPersistedCostRules (pure helpers) ---

$assertSame(
    0.0,
    OrderPersistedCostRules::calculateDefaultDeliveryCost(1000, 50, '100', 1500, 2),
    'rules: free delivery when cart meets threshold'
);

$assertSame(
    70.0,
    OrderPersistedCostRules::calculateDefaultDeliveryCost(0, 10, '5%', 1000, 2),
    'rules: weight cost plus percent of cart'
);

$assertSame(
    30.0,
    OrderPersistedCostRules::calculateDefaultPaymentCommission('3%', 1000),
    'rules: payment commission on cart-only base (#460)'
);

// --- ManagerOrderCostRecalculator::calculateBreakdown() ---

$createMs3 = static function (OrderCostRecalculatorModxStub $modx): MiniShop3 {
    $ref = new ReflectionClass(MiniShop3::class);
    /** @var MiniShop3 $instance */
    $instance = $ref->newInstanceWithoutConstructor();
    $instance->modx = $modx;
    $instance->utils = new Utils($instance);

    return $instance;
};

$orderStub = static function (array $fields): msOrder {
    return new class($fields) extends msOrder {
        public function __construct(private array $fields)
        {
        }

        public function get($key, $format = null, $formatTemplate = null): mixed
        {
            return $this->fields[$key] ?? null;
        }
    };
};

$deliveryStub = static function (array $fields): msDelivery {
    return new class($fields) extends msDelivery {
        public function __construct(private array $fields)
        {
        }

        public function get($key, $format = null, $formatTemplate = null): mixed
        {
            return $this->fields[$key] ?? null;
        }
    };
};

$paymentStub = static function (array $fields): msPayment {
    return new class($fields) extends msPayment {
        public function __construct(private array $fields)
        {
        }

        public function get($key, $format = null, $formatTemplate = null): mixed
        {
            return $this->fields[$key] ?? null;
        }
    };
};

$productStub = static function (array $fields): msOrderProduct {
    return new class($fields) extends msOrderProduct {
        public function __construct(private array $fields)
        {
        }

        public function get($key, $format = null, $formatTemplate = null): mixed
        {
            return $this->fields[$key] ?? null;
        }
    };
};

$modx = new OrderCostRecalculatorModxStub();
$ms3 = $createMs3($modx);
$recalculator = new ManagerOrderCostRecalculator($modx, $ms3);

$orderFreeDelivery = $orderStub([
    'id' => 10,
    'delivery_id' => 1,
    'payment_id' => 0,
    'delivery_cost' => 0,
]);

$modx->registerObject(msDelivery::class, $deliveryStub([
    'id' => 1,
    'class' => '',
    'free_delivery_amount' => 1000,
    'weight_price' => 50,
    'price' => '100',
]));

$modx->registerIteratorObject(msOrderProduct::class, $productStub([
    'order_id' => 10,
    'cost' => 1500,
    'weight' => 1,
    'count' => 1,
]));

$freeDeliveryBreakdown = $recalculator->calculateBreakdown($orderFreeDelivery);
$assertTrue($freeDeliveryBreakdown['success'] === true, 'breakdown: free delivery success');
$assertSame([], $freeDeliveryBreakdown['data']['warnings'], 'breakdown: free delivery no warnings');
$assertSame(1500.0, $freeDeliveryBreakdown['data']['breakdown']['cart_cost'], 'breakdown: cart cost');
$assertSame(0.0, $freeDeliveryBreakdown['data']['breakdown']['delivery_cost'], 'breakdown: free delivery cost');
$assertSame(1500.0, $freeDeliveryBreakdown['data']['breakdown']['cost'], 'breakdown: total with free delivery');

$modx2 = new OrderCostRecalculatorModxStub();
$ms3b = $createMs3($modx2);
$recalculator2 = new ManagerOrderCostRecalculator($modx2, $ms3b);

$orderFull = $orderStub([
    'id' => 20,
    'delivery_id' => 2,
    'payment_id' => 3,
    'delivery_cost' => 0,
]);

$modx2->registerObject(msDelivery::class, $deliveryStub([
    'id' => 2,
    'class' => '',
    'free_delivery_amount' => 0,
    'weight_price' => 10,
    'price' => '5%',
]));

$modx2->registerObject(msPayment::class, $paymentStub([
    'id' => 3,
    'class' => '',
    'price' => '3%',
]));

$modx2->registerIteratorObject(msOrderProduct::class, $productStub([
    'order_id' => 20,
    'cost' => 1000,
    'weight' => 2,
    'count' => 1,
]));

$fullBreakdown = $recalculator2->calculateBreakdown($orderFull);
$assertTrue($fullBreakdown['success'] === true, 'breakdown: percent delivery and payment success');
$assertSame([], $fullBreakdown['data']['warnings'], 'breakdown: default handlers no warnings');
$assertSame(70.0, $fullBreakdown['data']['breakdown']['delivery_cost'], 'breakdown: percent delivery cost');
$assertSame(30.0, $fullBreakdown['data']['breakdown']['payment_cost'], 'breakdown: payment fee on cart-only base');
$assertSame(1100.0, $fullBreakdown['data']['breakdown']['cost'], 'breakdown: integrated total');

$modx3 = new OrderCostRecalculatorModxStub();
$recalculator3 = new ManagerOrderCostRecalculator($modx3, $createMs3($modx3));

$orderCustom = $orderStub([
    'id' => 30,
    'delivery_id' => 4,
    'payment_id' => 0,
    'delivery_cost' => 0,
]);

$modx3->registerObject(msDelivery::class, $deliveryStub([
    'id' => 4,
    'class' => 'Vendor\\CustomDelivery',
    'free_delivery_amount' => 0,
    'weight_price' => 10,
    'price' => '100',
]));

$modx3->registerIteratorObject(msOrderProduct::class, $productStub([
    'order_id' => 30,
    'cost' => 500,
    'weight' => 1,
    'count' => 1,
]));

$customBreakdown = $recalculator3->calculateBreakdown($orderCustom);
$assertTrue($customBreakdown['success'] === true, 'breakdown: custom delivery still success');
$assertSame(
    [ManagerOrderCostRecalculator::WARNING_DELIVERY_MANUAL_REQUIRED],
    $customBreakdown['data']['warnings'],
    'breakdown: custom delivery emits manual warning'
);

fwrite(STDOUT, "OK ManagerOrderCostRecalculatorRulesTest\n");
exit(0);
