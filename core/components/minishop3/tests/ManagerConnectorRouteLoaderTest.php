<?php

/**
 * Manager connector must not load storefront routes (#384).
 *
 * Run: php tests/ManagerConnectorRouteLoaderTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Processors\Api\ManagerConnectorRouteLoader;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame(true, ManagerConnectorRouteLoader::isStorefrontRoute('/api/v1/cart/get'), 'cart/get');
$assertSame(true, ManagerConnectorRouteLoader::isStorefrontRoute('/api/v1/health'), 'health');
$assertSame(false, ManagerConnectorRouteLoader::isStorefrontRoute('/api/mgr/orders'), 'mgr orders');
$assertSame(false, ManagerConnectorRouteLoader::isStorefrontRoute('/api/mgr/customers'), 'mgr customers');
$assertSame(false, ManagerConnectorRouteLoader::isStorefrontRoute(''), 'empty');

$forbidden = ManagerConnectorRouteLoader::forbiddenPathSuffixes();
foreach (['config/routes/web.php', 'config/ms3.routes.d/web', 'config/ms3_routes_web.custom.php'] as $suffix) {
    if (!in_array($suffix, $forbidden, true)) {
        $fail("forbidden list missing {$suffix}");
    }
}

$webLoadPattern = "/loadRoutes\\s*\\(\\s*\\\$webRoutesFile|config\\/routes\\/web\\.php|coreAddonRoutesDirectory\\(\\s*'web'\\s*\\)|ms3_routes_web\\.custom|ms3\\.routes\\.d\\/web/";

// Regression: connector dispatch classes must not load web routes
foreach ([
    'Index.php',
    'Router.php',
    'ProcessesManagerConnectorRouteTrait.php',
] as $file) {
    $src = file_get_contents(__DIR__ . '/../src/Processors/Api/' . $file);
    if ($src === false) {
        $fail("cannot read {$file}");
    }
    if (preg_match($webLoadPattern, $src)) {
        $fail("{$file} still loads web routes");
    }
}

// load() itself must be manager-only (denylist strings live only in forbiddenPathSuffixes)
$loaderSrc = file_get_contents(__DIR__ . '/../src/Processors/Api/ManagerConnectorRouteLoader.php');
if ($loaderSrc === false) {
    $fail('cannot read ManagerConnectorRouteLoader.php');
}
$loadStart = strpos($loaderSrc, 'public static function load(');
$loadEnd = strpos($loaderSrc, 'public static function isStorefrontRoute(');
if ($loadStart === false || $loadEnd === false || $loadEnd <= $loadStart) {
    $fail('cannot isolate load() body');
}
$loadBody = substr($loaderSrc, $loadStart, $loadEnd - $loadStart);
foreach ($forbidden as $suffix) {
    if (str_contains($loadBody, $suffix)) {
        $fail("load() references forbidden path {$suffix}");
    }
}
if (str_contains($loadBody, "coreAddonRoutesDirectory('web')")) {
    $fail('load() still opens web addon directory');
}
if (!str_contains($loadBody, "coreAddonRoutesDirectory('manager')")
    && !str_contains($loadBody, 'ms3.routes.d' . DIRECTORY_SEPARATOR . 'manager')
    && !str_contains($loadBody, "ms3.routes.d') . DIRECTORY_SEPARATOR . 'manager")
    && !str_contains($loadBody, "ms3.routes.d' . DIRECTORY_SEPARATOR . 'manager")) {
    // accept either coreAddonRoutesDirectory('manager') or concatenated path
    if (!str_contains($loadBody, 'ms3.routes.d') || !str_contains($loadBody, 'manager')) {
        $fail('load() must load manager addon routes directory');
    }
}

$traitSrc = file_get_contents(__DIR__ . '/../src/Processors/Api/ProcessesManagerConnectorRouteTrait.php');
if ($traitSrc === false) {
    $fail('cannot read ProcessesManagerConnectorRouteTrait.php');
}
if (!str_contains($traitSrc, 'ManagerConnectorRouteLoader::load')) {
    $fail('dispatch trait must use ManagerConnectorRouteLoader::load');
}
if (!str_contains($traitSrc, 'isStorefrontRoute')) {
    $fail('dispatch trait must reject storefront routes via isStorefrontRoute');
}
if (!str_contains($traitSrc, 'System routes not found:')) {
    $fail('dispatch trait must surface missing manager.php to the client');
}

foreach (['Index.php', 'Router.php'] as $file) {
    $src = file_get_contents(__DIR__ . '/../src/Processors/Api/' . $file);
    if ($src === false) {
        $fail("cannot read {$file}");
    }
    if (!str_contains($src, 'ProcessesManagerConnectorRouteTrait')) {
        $fail("{$file} must use ProcessesManagerConnectorRouteTrait");
    }
}

fwrite(STDOUT, "OK: ManagerConnectorRouteLoaderTest\n");
