<?php

/**
 * Smoke: extra-fields routes dispatch to ExtraFieldsController (#355).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL ExtraFieldsControllerRoutesTest: {$message}\n");
    exit(1);
};

$routesPath = $root . '/config/routes/manager.php';
if (!is_readable($routesPath)) {
    $fail('manager.php not readable');
}

$src = file_get_contents($routesPath);
if ($src === false) {
    $fail('manager.php read failed');
}

$start = strpos($src, "\$router->group('/extra-fields'");
if ($start === false) {
    $fail("extra-fields group not found");
}

$nextGroup = strpos($src, "\$router->group('/customers'", $start);
if ($nextGroup === false) {
    $fail('anchor after /extra-fields (/customers) not found');
}

$block = substr($src, $start, $nextGroup - $start);

if (str_contains($block, 'getObject')) {
    $fail('extra-fields block must not call getObject');
}

if (str_contains($block, "file_get_contents('php://input')")) {
    $fail('extra-fields block must not parse request body');
}

if (str_contains($block, 'new \\MiniShop3\\Services\\ExtraFieldsService')) {
    $fail('extra-fields block must not instantiate ExtraFieldsService');
}

if (!str_contains($block, 'ExtraFieldsController')) {
    $fail('extra-fields block must dispatch to ExtraFieldsController');
}

$controllerPath = $root . '/src/Controllers/Api/Manager/ExtraFieldsController.php';
if (!is_readable($controllerPath)) {
    $fail('ExtraFieldsController.php missing');
}

$controllerSrc = file_get_contents($controllerPath);
if ($controllerSrc === false) {
    $fail('ExtraFieldsController read failed');
}

foreach (['getList', 'get', 'create', 'update', 'delete'] as $method) {
    if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $controllerSrc)) {
        $fail("ExtraFieldsController missing method {$method}");
    }
}

if (!str_contains($controllerSrc, "services->get('ms3_extra_fields')")) {
    $fail('ExtraFieldsController must resolve ms3_extra_fields from DI');
}

$registryPath = $root . '/src/ServiceRegistry.php';
$registry = file_get_contents($registryPath);
if ($registry === false || !str_contains($registry, "'ms3_extra_fields'")) {
    $fail('ServiceRegistry must register ms3_extra_fields');
}

$servicePath = $root . '/src/Services/ExtraFieldsService.php';
$serviceSrc = file_get_contents($servicePath);
if ($serviceSrc === false || !str_contains($serviceSrc, 'function getField(')) {
    $fail('ExtraFieldsService must expose getField()');
}

echo "OK ExtraFieldsControllerRoutesTest\n";
