<?php

/**
 * Smoke checks for OrdersController decomposition into services.
 *
 * Run: php tests/ManagerOrdersDecompositionTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Api\Manager\OrdersController;
use MiniShop3\ServiceRegistry;
use MiniShop3\Services\Order\ManagerOrderListService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$controllerPath = realpath(__DIR__ . '/../src/Controllers/Api/Manager/OrdersController.php');
if ($controllerPath === false || !is_file($controllerPath)) {
    $fail('OrdersController.php not found');
}

$lineCount = count(file($controllerPath, FILE_IGNORE_NEW_LINES));
if ($lineCount >= 1000) {
    $fail("OrdersController is too large: {$lineCount} lines (must be < 1000)");
}

$requiredServiceFiles = [
    __DIR__ . '/../src/Services/Order/ManagerOrderPresenter.php',
    __DIR__ . '/../src/Services/Order/ManagerOrderListService.php',
    __DIR__ . '/../src/Services/Order/ManagerOrderMutationService.php',
    __DIR__ . '/../src/Services/Order/ManagerOrderProductsService.php',
];

foreach ($requiredServiceFiles as $path) {
    if (!is_file($path)) {
        $fail('Missing service file: ' . basename($path));
    }
}

$controllerKeys = OrdersController::getDirectFilterKeys();
$serviceKeys = ManagerOrderListService::getDirectFilterKeys();
if ($controllerKeys !== $serviceKeys) {
    $fail('OrdersController::getDirectFilterKeys must delegate list service keys');
}

$registryReflection = new ReflectionClass(ServiceRegistry::class);
$registry = $registryReflection->newInstanceWithoutConstructor();
$defaultServices = $registryReflection->getProperty('defaultServices');
$defaultServices->setAccessible(true);
$services = $defaultServices->getValue($registry);

$requiredKeys = [
    'ms3_manager_order_presenter',
    'ms3_manager_order_list',
    'ms3_manager_order_mutation',
    'ms3_manager_order_products',
    'ms3_manager_order_cost_recalculator',
    'ms3_extra_fields',
];

foreach ($requiredKeys as $key) {
    if (!isset($services[$key])) {
        $fail("ServiceRegistry defaultServices missing key: {$key}");
    }
}

fwrite(STDOUT, "OK ManagerOrdersDecompositionTest ({$lineCount} lines)\n");
exit(0);
