<?php

/**
 * Unit checks for shared deliveries/payments reference CRUD config (#356).
 *
 * Run: php tests/ReferenceResourceCrudConfigTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msPayment;
use MiniShop3\Services\Grid\ManagerListFilterPolicy;
use MiniShop3\Services\Reference\Ms3ReferenceCrudService;
use MiniShop3\Services\Reference\ReferenceResourceConfig;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $value, string $case) use ($fail): void {
    if (!$value) {
        $fail($case . ': expected true');
    }
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$deliveries = ReferenceResourceConfig::forDeliveries();
$payments = ReferenceResourceConfig::forPayments();

$assertSame(msDelivery::class, $deliveries->modelClass, 'deliveries model');
$assertSame(msPayment::class, $payments->modelClass, 'payments model');

$assertTrue(
    in_array('validation_rules', $deliveries->allowedFields, true),
    'deliveries keep validation_rules'
);
$assertTrue(
    in_array('free_delivery_amount', $deliveries->allowedFields, true),
    'deliveries keep free_delivery_amount'
);
$assertTrue(
    !in_array('validation_rules', $payments->allowedFields, true),
    'payments must not expose validation_rules'
);
$assertTrue(
    !in_array('free_delivery_amount', $payments->allowedFields, true),
    'payments must not expose free_delivery_amount'
);

$assertSame('payments', $deliveries->embedLinksKey, 'deliveries embed payments on get');
$assertSame(null, $payments->embedLinksKey, 'payments do not embed deliveries on get');
$assertTrue(
    in_array('weight_price', $deliveries->floatFields, true),
    'deliveries cast weight_price'
);
$assertSame([], $payments->floatFields, 'payments have no float casts');

$assertSame(
    ManagerListFilterPolicy::DELIVERY_FILTER_MAP,
    $deliveries->filterMap,
    'delivery filter map'
);
$assertSame(
    ManagerListFilterPolicy::PAYMENT_FILTER_MAP,
    $payments->filterMap,
    'payment filter map'
);

// Controllers stay thin and keep public method names (route BC).
$assertHasMethods = static function (string $class, array $methods) use ($assertTrue): void {
    $available = get_class_methods($class);
    foreach ($methods as $method) {
        $assertTrue(in_array($method, $available, true), "{$class}::{$method}");
    }
};

$assertHasMethods(\MiniShop3\Controllers\Api\Manager\DeliveriesController::class, [
    'getList',
    'get',
    'create',
    'update',
    'delete',
    'sort',
    'bulkDelete',
    'updatePositions',
    'getPayments',
    'addPayment',
    'removePayment',
    'getActiveDropdown',
]);

$assertHasMethods(\MiniShop3\Controllers\Api\Manager\PaymentsController::class, [
    'getList',
    'get',
    'create',
    'update',
    'delete',
    'sort',
    'bulkDelete',
    'updatePositions',
    'getDeliveries',
    'addDelivery',
    'removeDelivery',
    'getActiveDropdown',
]);

$assertTrue(
    class_exists(Ms3ReferenceCrudService::class),
    'Ms3ReferenceCrudService exists'
);

// Controllers should not re-implement CRUD bodies (source size / shared service usage).
$deliverySrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Manager/DeliveriesController.php');
$paymentSrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Manager/PaymentsController.php');
$serviceSrc = file_get_contents(__DIR__ . '/../src/Services/Reference/Ms3ReferenceCrudService.php');
if ($deliverySrc === false || $paymentSrc === false || $serviceSrc === false) {
    $fail('cannot read source files');
}

$assertTrue(
    str_contains($deliverySrc, 'Ms3ReferenceCrudService'),
    'DeliveriesController uses Ms3ReferenceCrudService'
);
$assertTrue(
    str_contains($paymentSrc, 'Ms3ReferenceCrudService'),
    'PaymentsController uses Ms3ReferenceCrudService'
);
$assertTrue(
    str_contains($serviceSrc, '$returnAll = $limit === 0'),
    'getList treats limit=0 as all rows (PaymentsGrid deliveries load)'
);
$assertTrue(
    !str_contains($serviceSrc, 'allowLimitZero'),
    'no allowLimitZero mode flag'
);
$assertTrue(
    substr_count($deliverySrc, "\n") < 200,
    'DeliveriesController stays thin (<200 lines)'
);
$assertTrue(
    substr_count($paymentSrc, "\n") < 200,
    'PaymentsController stays thin (<200 lines)'
);

fwrite(STDOUT, "OK ReferenceResourceCrudConfigTest\n");
exit(0);
