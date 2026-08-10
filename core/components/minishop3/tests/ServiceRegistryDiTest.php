<?php

/**
 * DI registry smoke — OptionService wiring (#363, #531/#532) + ServiceRegistryFactories (#345).
 *
 * Run: php tests/ServiceRegistryDiTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\ServiceRegistry;
use MiniShop3\Services\Category\CategoryOptionService;
use MiniShop3\Services\Option\OptionCategoryService;
use MiniShop3\Services\Option\OptionService;
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
    'ms3_option_category_service',
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

$factoriesSrc = file_get_contents(__DIR__ . '/../src/ServiceRegistryFactories.php');
if ($factoriesSrc === false || $factoriesSrc === '') {
    $fail('unable to read ServiceRegistryFactories.php');
}

if (!str_contains($factoriesSrc, "'ms3_option_service'")) {
    $fail('ServiceRegistryFactories must wire ms3_option_service');
}

if (!str_contains($factoriesSrc, "services->get('ms3_option_category_service')")) {
    $fail('ServiceRegistryFactories must resolve ms3_option_category_service for OptionService (#531/#532)');
}

if (str_contains($factoriesSrc, "services->get('ms3_category_option_service')")) {
    $fail('ms3_option_service factory must not inject ms3_category_option_service (wrong class) (#531/#532)');
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
if (!preg_match(
    '/OptionLoaderService\s+\$loader,\s*OptionSyncService\s+\$sync,\s*OptionCategoryService\s+\$category/s',
    $optionServiceSrc
)) {
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
    'ms3_option_category_service',
    'ms3_category_option_service',
    'ms3_manager_order_cost_recalculator',
] as $key) {
    if (!str_contains($example, "'{$key}'")) {
        $fail("ms3.services.example.php must document {$key} override");
    }
}

$modx = new modX();
$registry = new class ($modx) extends ServiceRegistry {
    protected function loadCustomServices(): void
    {
        // skip filesystem config loading in unit test
    }

    public function defaultServiceMap(): array
    {
        return $this->defaultServices;
    }
};

$registered = $registry->getRegisteredServices();
$registeredSet = array_flip($registered);
$defaults = $registry->defaultServiceMap();

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
if (!in_array('ms3_option_category_service', $optionDeps, true)) {
    $fail('ms3_option_service must depend on ms3_option_category_service (#531/#532)');
}
if (in_array('ms3_category_option_service', $optionDeps, true)) {
    $fail('ms3_option_service must not depend on ms3_category_option_service (#531/#532)');
}

if (($defaults['ms3_option_category_service']['class'] ?? null) !== OptionCategoryService::class) {
    $fail('ms3_option_category_service must map to Option\\OptionCategoryService');
}
if (($defaults['ms3_category_option_service']['class'] ?? null) !== CategoryOptionService::class) {
    $fail('ms3_category_option_service must map to Category\\CategoryOptionService');
}

$ctor = new ReflectionMethod(OptionService::class, '__construct');
$params = $ctor->getParameters();
if (count($params) < 4) {
    $fail('OptionService::__construct must have 4 parameters');
}
$categoryType = $params[3]->getType();
if (!$categoryType instanceof ReflectionNamedType || $categoryType->getName() !== OptionCategoryService::class) {
    $fail('OptionService 4th ctor param must be typed OptionCategoryService');
}

$categoryDepClass = $defaults['ms3_option_category_service']['class'] ?? null;
if ($categoryDepClass !== $categoryType->getName()) {
    $fail('ms3_option_category_service class must match OptionService 4th ctor type');
}

fwrite(STDOUT, "OK ServiceRegistryDiTest\n");
exit(0);
