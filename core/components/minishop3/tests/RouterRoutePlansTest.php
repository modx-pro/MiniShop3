<?php

/**
 * Behavioral checks for manager vs web route plans (#384).
 *
 * Run: php tests/RouterRoutePlansTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Router\Router;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame(true, Router::isStorefrontRoute('/api/v1/cart/get'), 'cart/get');
$assertSame(true, Router::isStorefrontRoute('/api/v1/health'), 'health');
$assertSame(false, Router::isStorefrontRoute('/api/mgr/orders'), 'mgr orders');
$assertSame(false, Router::isStorefrontRoute(''), 'empty');

$component = '/tmp/ms3-component';
$core = '/tmp/ms3-core';

$managerPlan = Router::managerRoutePlan($component, $core);
$assertSame(3, count($managerPlan), 'manager plan size');
$assertSame('file', $managerPlan[0]['kind'], 'manager[0] kind');
$assertSame(true, $managerPlan[0]['required'], 'manager[0] required');
$assertSame(
    $component . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'manager.php',
    $managerPlan[0]['path'],
    'manager.php path'
);
$assertSame(
    $core . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ms3_routes_manager.custom.php',
    $managerPlan[1]['path'],
    'manager custom path'
);
$assertSame('dir', $managerPlan[2]['kind'], 'manager addon kind');
$assertSame(
    $core . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ms3.routes.d' . DIRECTORY_SEPARATOR . 'manager',
    $managerPlan[2]['path'],
    'manager addon dir'
);

foreach ($managerPlan as $i => $item) {
    $path = $item['path'];
    foreach (['web.php', 'ms3_routes_web', 'ms3.routes.d' . DIRECTORY_SEPARATOR . 'web'] as $forbidden) {
        if (str_contains($path, $forbidden)) {
            $fail("manager plan[{$i}] contains forbidden fragment {$forbidden}: {$path}");
        }
    }
}

$webPlan = Router::webRoutePlan($component, $core);
$assertSame(3, count($webPlan), 'web plan size');
$assertSame(
    $component . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php',
    $webPlan[0]['path'],
    'web.php path'
);
$assertSame(
    $core . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ms3.routes.d' . DIRECTORY_SEPARATOR . 'web',
    $webPlan[2]['path'],
    'web addon dir'
);

foreach ($webPlan as $i => $item) {
    $path = $item['path'];
    foreach (['manager.php', 'ms3_routes_manager', 'ms3.routes.d' . DIRECTORY_SEPARATOR . 'manager'] as $forbidden) {
        if (str_contains($path, $forbidden)) {
            $fail("web plan[{$i}] contains forbidden fragment {$forbidden}: {$path}");
        }
    }
}

// Plans must be disjoint (no shared path)
$managerPaths = array_column($managerPlan, 'path');
$webPaths = array_column($webPlan, 'path');
$overlap = array_intersect($managerPaths, $webPaths);
if ($overlap !== []) {
    $fail('manager and web plans overlap: ' . implode(', ', $overlap));
}

// Connector trait must use loadManagerRoutes + isStorefrontRoute (wiring check)
$traitSrc = file_get_contents(__DIR__ . '/../src/Processors/Api/ProcessesManagerConnectorRouteTrait.php');
if ($traitSrc === false) {
    $fail('cannot read ProcessesManagerConnectorRouteTrait.php');
}
if (!str_contains($traitSrc, 'loadManagerRoutes')) {
    $fail('trait must call loadManagerRoutes()');
}
if (!str_contains($traitSrc, 'isStorefrontRoute')) {
    $fail('trait must reject storefront via isStorefrontRoute()');
}
if (!str_contains($traitSrc, 'System routes not found:')) {
    $fail('trait must return user-visible message when manager.php is missing');
}
// Early rejects set HTTP status (storefront 404), not only JSON code
if (!str_contains($traitSrc, 'http_response_code(404)')) {
    $fail('trait must call http_response_code(404) when rejecting storefront routes');
}
if (!str_contains($traitSrc, 'catch (\\Throwable')) {
    $fail('trait must catch \\Throwable so TypeError stays JSON (#531/#532)');
}
if (str_contains($traitSrc, 'loadWebRoutes') || str_contains($traitSrc, 'ManagerConnectorRouteLoader')) {
    $fail('trait must not load web routes or use deleted ManagerConnectorRouteLoader');
}

if (file_exists(__DIR__ . '/../src/Processors/Api/ManagerConnectorRouteLoader.php')) {
    $fail('ManagerConnectorRouteLoader.php should be deleted');
}

$apiPhpPath = dirname(__DIR__, 4) . '/assets/components/minishop3/api.php';
$apiPhp = file_get_contents($apiPhpPath);
if ($apiPhp === false || !str_contains($apiPhp, 'loadWebRoutes')) {
    $fail('api.php must use loadWebRoutes() (' . $apiPhpPath . ')');
}

fwrite(STDOUT, "OK: RouterRoutePlansTest\n");
