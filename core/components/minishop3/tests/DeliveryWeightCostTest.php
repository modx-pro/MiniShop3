<?php

/**
 * Default delivery cost takes weight from the order products (weight_price × Σ weight × count).
 *
 * Run: php tests/DeliveryWeightCostTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/XpdoStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Delivery\DefaultDelivery;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MODX\Revolution\modX;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertFloat = static function (float $expected, float $actual, string $case) use ($fail): void {
    if (abs($expected - $actual) > 0.000001) {
        $fail($case . ': expected ' . $expected . ', got ' . $actual);
    }
};

$ref = new ReflectionClass(MiniShop3::class);
/** @var MiniShop3 $ms3 */
$ms3 = $ref->newInstanceWithoutConstructor();
$ms3->modx = new modX();

$delivery = static function (array $fields): msDelivery {
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

$product = static function (float $weight, int $count): msOrderProduct {
    return new class(['weight' => $weight, 'count' => $count]) extends msOrderProduct {
        public function __construct(private array $fields)
        {
        }

        public function get($key, $format = null, $formatTemplate = null): mixed
        {
            return $this->fields[$key] ?? null;
        }
    };
};

$order = static function (array $products): msOrder {
    return new class($products) extends msOrder {
        public function __construct(private array $products)
        {
        }

        public function getMany($alias, $criteria = null, $cacheFlag = true)
        {
            return $alias === 'Products' ? $this->products : [];
        }
    };
};

$controller = new DefaultDelivery($ms3);
$post = $delivery(['id' => 4, 'price' => '390', 'weight_price' => 60, 'free_delivery_amount' => 0]);

// 0.4 kg × 2 + 1 kg × 1 = 1.8 kg → 390 + 60 × 1.8
$assertFloat(498.0, $controller->getCost($order([$product(0.4, 2), $product(1.0, 1)]), $post, 2000.0), 'weight × count');
$assertFloat(390.0, $controller->getCost($order([]), $post, 0.0), 'empty order: base price only');

$courier = $delivery(['id' => 2, 'price' => '350', 'weight_price' => 60, 'free_delivery_amount' => 5000]);
$assertFloat(0.0, $controller->getCost($order([$product(3.0, 1)]), $courier, 6000.0), 'free delivery threshold wins over weight');
$assertFloat(530.0, $controller->getCost($order([$product(3.0, 1)]), $courier, 1000.0), 'below threshold: price + weight');

$percent = $delivery(['id' => 5, 'price' => '10%', 'weight_price' => 100, 'free_delivery_amount' => 0]);
$assertFloat(150.0, $controller->getCost($order([$product(0.5, 1)]), $percent, 1000.0), 'percent price + weight');

echo "OK DeliveryWeightCostTest\n";
