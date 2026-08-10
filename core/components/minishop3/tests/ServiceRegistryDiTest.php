<?php

/**
 * DI registry smoke for #363 — OptionSync/Loader/Category + ManagerOrderCostRecalculator.
 *
 * Run: php tests/ServiceRegistryDiTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\ServiceRegistry;
use MODX\Revolution\modX;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$registrySrc = file_get_contents(__DIR__ . '/../src/ServiceRegistry.php');
if ($registrySrc === false || $registrySrc === '') {
    $fail('unable to read ServiceRegistry.php');
}

foreach ([
    'ms3_option_loader',
    'ms3_option_sync',
    'ms3_category_option_service',
    'ms3_manager_order_cost_recalculator',
] as $key) {
    if (!str_contains($registrySrc, "'{$key}'")) {
        $fail("ServiceRegistry missing {$key}");
    }
}

if (!str_contains($registrySrc, "'ms3_option_service'")) {
    $fail('ms3_option_service must stay registered');
}

if (!str_contains($registrySrc, 'SERVICES_WITH_DEPENDENCIES')) {
    $fail('ServiceRegistry must declare SERVICES_WITH_DEPENDENCIES factory map');
}

if (!str_contains($registrySrc, "case 'ms3_option_service':")) {
    $fail('registerServiceWithDependencies must wire ms3_option_service');
}

if (!str_contains($registrySrc, "services->get('ms3_category_option_service')")) {
    $fail('registerServiceWithDependencies must resolve ms3_category_option_service for OptionService');
}

if (!str_contains($registrySrc, 'SERVICES_WITH_MODX_AND_MS3')) {
    $fail('ServiceRegistry must declare SERVICES_WITH_MODX_AND_MS3 factory map');
}

$optionServiceSrc = file_get_contents(__DIR__ . '/../src/Services/Option/OptionService.php');
if ($optionServiceSrc === false) {
    $fail('unable to read OptionService.php');
}
if (preg_match('/new\s+OptionLoaderService\s*\(/', $optionServiceSrc)) {
    $fail('OptionService must not instantiate OptionLoaderService directly');
}
if (preg_match('/new\s+OptionSyncService\s*\(/', $optionServiceSrc)) {
    $fail('OptionService must not instantiate OptionSyncService directly');
}
if (preg_match('/new\s+OptionCategoryService\s*\(/', $optionServiceSrc)) {
    $fail('OptionService must not instantiate OptionCategoryService directly');
}
if (!preg_match('/OptionLoaderService\s+\$loader,\s*OptionSyncService\s+\$sync,\s*OptionCategoryService\s+\$category/s', $optionServiceSrc)) {
    $fail('OptionService constructor must accept loader/sync/category from DI');
}

$ordersCtrlSrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Manager/OrdersController.php');
if ($ordersCtrlSrc === false) {
    $fail('unable to read OrdersController.php');
}
if (preg_match('/new\s+ManagerOrderCostRecalculator\s*\(/', $ordersCtrlSrc)) {
    $fail('OrdersController must not instantiate ManagerOrderCostRecalculator directly');
}
if (!str_contains($ordersCtrlSrc, "services->get('ms3_manager_order_cost_recalculator')")) {
    $fail('OrdersController must resolve manager cost recalculator from DI');
}

$example = file_get_contents(__DIR__ . '/../config/ms3.services.example.php');
if ($example === false) {
    $fail('unable to read ms3.services.example.php');
}
foreach ([
    'ms3_option_loader',
    'ms3_option_sync',
    'ms3_category_option_service',
    'ms3_manager_order_cost_recalculator',
] as $key) {
    if (!str_contains($example, "'{$key}'")) {
        $fail("ms3.services.example.php must document {$key} override");
    }
}

// Behavior test: every factory-map key and every declared dependency must be
// a registered service key, so the DI wiring can actually resolve at runtime
// (#363 review: verify factory map keys exist).
$modx = new modX();
$registry = new class ($modx) extends ServiceRegistry {
    protected function loadCustomServices(): void
    {
        // skip filesystem config loading in unit test
    }
};
$registered = $registry->getRegisteredServices();
$registeredSet = array_flip($registered);

$assertRegistered = static function (string $key, string $map) use ($registeredSet, $fail): void {
    if (!isset($registeredSet[$key])) {
        $fail("{$map} references unregistered service key: {$key}");
    }
};

foreach (ServiceRegistry::CONTROLLERS_WITH_MS3_ONLY as $key) {
    $assertRegistered($key, 'CONTROLLERS_WITH_MS3_ONLY');
}
foreach (ServiceRegistry::SERVICES_WITH_MODX_AND_MS3 as $key) {
    $assertRegistered($key, 'SERVICES_WITH_MODX_AND_MS3');
    if ($key !== 'ms3_manager_order_cost_recalculator') {
        continue;
    }
}
if (!in_array('ms3_manager_order_cost_recalculator', ServiceRegistry::SERVICES_WITH_MODX_AND_MS3, true)) {
    $fail('ms3_manager_order_cost_recalculator must use modX+MiniShop3 factory');
}
foreach (ServiceRegistry::SERVICES_WITH_DEPENDENCIES as $key) {
    $assertRegistered($key, 'SERVICES_WITH_DEPENDENCIES');
}
foreach (ServiceRegistry::SERVICE_DEPENDENCIES as $service => $deps) {
    $assertRegistered($service, 'SERVICE_DEPENDENCIES');
    foreach ($deps as $dep) {
        $assertRegistered($dep, "SERVICE_DEPENDENCIES[{$service}]");
    }
}

$optionDeps = ServiceRegistry::SERVICE_DEPENDENCIES['ms3_option_service'] ?? [];
if (!in_array('ms3_option_loader', $optionDeps, true)) {
    $fail('ms3_option_service must depend on ms3_option_loader');
}
if (!in_array('ms3_option_sync', $optionDeps, true)) {
    $fail('ms3_option_service must depend on ms3_option_sync');
}
if (!in_array('ms3_category_option_service', $optionDeps, true)) {
    $fail('ms3_option_service must depend on ms3_category_option_service');
}

fwrite(STDOUT, "OK ServiceRegistryDiTest\n");
exit(0);
