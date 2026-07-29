<?php

/**
 * DI registry smoke for #363 — OptionSync/Loader + ManagerOrderCostRecalculator.
 *
 * Run: php tests/ServiceRegistryDiTest.php
 */

declare(strict_types=1);

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
    'ms3_manager_order_cost_recalculator',
] as $key) {
    if (!str_contains($registrySrc, "'{$key}'")) {
        $fail("ServiceRegistry missing {$key}");
    }
}

if (!str_contains($registrySrc, "'ms3_option_service'")) {
    $fail('ms3_option_service must stay registered');
}

if (!preg_match("/servicesWithDependencies\s*=\s*\[[^\]]*'ms3_option_service'/s", $registrySrc)) {
    $fail('ms3_option_service must resolve via servicesWithDependencies');
}

if (!str_contains($registrySrc, "case 'ms3_option_service':")) {
    $fail('registerServiceWithDependencies must wire ms3_option_service');
}

if (!preg_match("/servicesWithModxAndMs3\s*=\s*\[[^\]]*'ms3_manager_order_cost_recalculator'/s", $registrySrc)) {
    $fail('ms3_manager_order_cost_recalculator must use modX+MiniShop3 factory');
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
if (!str_contains($optionServiceSrc, 'OptionLoaderService $loader, OptionSyncService $sync')) {
    $fail('OptionService constructor must accept loader/sync from DI');
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
foreach (['ms3_option_loader', 'ms3_option_sync', 'ms3_manager_order_cost_recalculator'] as $key) {
    if (!str_contains($example, "'{$key}'")) {
        $fail("ms3.services.example.php must document {$key} override");
    }
}

fwrite(STDOUT, "OK ServiceRegistryDiTest\n");
exit(0);
