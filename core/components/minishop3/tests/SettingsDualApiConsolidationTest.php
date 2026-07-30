<?php

/**
 * Regression: Settings dual-API consolidation (#346).
 *
 * - Duplicate Delivery↔Payment member Disable/Enable processors removed
 * - SettingsComboListService registered and used by GetList combo adapters
 * - payments-active route exists alongside deliveries-active
 *
 * Run: php tests/SettingsDualApiConsolidationTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $cond, string $case) use ($fail): void {
    if (!$cond) {
        $fail($case);
    }
};

$root = dirname(__DIR__);
$src = $root . '/src';

$removed = [
    $src . '/Processors/Settings/Delivery/Payments/Disable.php',
    $src . '/Processors/Settings/Delivery/Payments/Enable.php',
    $src . '/Processors/Settings/Delivery/Payments/GetList.php',
    $src . '/Processors/Settings/Delivery/Payments/Multiple.php',
    $src . '/Processors/Settings/Payment/Deliveries/Disable.php',
    $src . '/Processors/Settings/Payment/Deliveries/Enable.php',
    $src . '/Processors/Settings/Payment/Deliveries/GetList.php',
    $src . '/Processors/Settings/Payment/Deliveries/Multiple.php',
];

foreach ($removed as $path) {
    $assertTrue(!is_file($path), 'removed processor still present: ' . $path);
}

$serviceFile = $src . '/Services/Settings/SettingsComboListService.php';
$assertTrue(is_file($serviceFile), 'SettingsComboListService missing');

$registry = file_get_contents($src . '/ServiceRegistry.php');
$assertTrue(
    is_string($registry) && str_contains($registry, 'ms3_settings_combo_list'),
    'ServiceRegistry missing ms3_settings_combo_list'
);

foreach (['Delivery', 'Payment', 'Vendor'] as $entity) {
    $getList = file_get_contents($src . "/Processors/Settings/{$entity}/GetList.php");
    $assertTrue(
        is_string($getList) && str_contains($getList, 'SettingsComboListService'),
        "{$entity} GetList must delegate combo mode to SettingsComboListService"
    );
}

$routes = file_get_contents($root . '/config/routes/manager.php');
$assertTrue(
    is_string($routes) && str_contains($routes, "/payments-active"),
    'manager.php missing /payments-active route'
);
$assertTrue(
    is_string($routes) && str_contains($routes, "/deliveries-active"),
    'manager.php missing /deliveries-active route'
);

$references = file_get_contents($src . '/Controllers/Api/ReferencesController.php');
$assertTrue(
    is_string($references) && str_contains($references, 'SettingsComboListService'),
    'ReferencesController must use SettingsComboListService for vendors'
);

$deliveries = file_get_contents($src . '/Controllers/Api/Manager/DeliveriesController.php');
$assertTrue(
    is_string($deliveries) && str_contains($deliveries, 'SettingsComboListService'),
    'DeliveriesController must use SettingsComboListService for active dropdown'
);

$payments = file_get_contents($src . '/Controllers/Api/Manager/PaymentsController.php');
$assertTrue(
    is_string($payments) && str_contains($payments, 'getActiveDropdown'),
    'PaymentsController must expose getActiveDropdown'
);

$repoBaseline = dirname(__DIR__, 4) . '/phpstan-baseline.neon';
if (is_file($repoBaseline)) {
    $baseline = (string) file_get_contents($repoBaseline);
    $assertTrue(
        !str_contains($baseline, 'Delivery/Payments/Disable.php'),
        'phpstan-baseline still references deleted Delivery/Payments/Disable'
    );
    $assertTrue(
        !str_contains($baseline, 'Payment/Deliveries/Disable.php'),
        'phpstan-baseline still references deleted Payment/Deliveries/Disable'
    );
}

fwrite(STDOUT, "OK SettingsDualApiConsolidationTest\n");
exit(0);
