<?php

/**
 * Smoke: ModelFieldsController → ModelField(Section)Service (#364).
 *
 * Run: php tests/ModelFieldsServiceStructureTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL ModelFieldsServiceStructureTest: {$message}\n");
    exit(1);
};

$fieldServicePath = $root . '/src/Services/ModelField/ModelFieldService.php';
$sectionServicePath = $root . '/src/Services/ModelField/ModelFieldSectionService.php';
$controllerPath = $root . '/src/Controllers/Api/Manager/ModelFieldsController.php';
$registryPath = $root . '/src/ServiceRegistry.php';

foreach ([$fieldServicePath, $sectionServicePath, $controllerPath, $registryPath] as $path) {
    if (!is_readable($path)) {
        $fail(basename($path) . ' missing or unreadable');
    }
}

$fieldServiceSrc = file_get_contents($fieldServicePath);
$sectionServiceSrc = file_get_contents($sectionServicePath);
$controllerSrc = file_get_contents($controllerPath);
$registrySrc = file_get_contents($registryPath);

if ($fieldServiceSrc === false || $sectionServiceSrc === false || $controllerSrc === false || $registrySrc === false) {
    $fail('failed to read one or more PHP sources');
}

if (!str_contains($fieldServiceSrc, 'class ModelFieldService')) {
    $fail('ModelFieldService class not found');
}
if (!str_contains($sectionServiceSrc, 'class ModelFieldSectionService')) {
    $fail('ModelFieldSectionService class not found');
}

$controllerLines = substr_count($controllerSrc, "\n") + (str_ends_with($controllerSrc, "\n") ? 0 : 1);
if ($controllerLines >= 400) {
    $fail("ModelFieldsController LOC must be < 400, got {$controllerLines}");
}

if (str_contains($controllerSrc, 'newQuery(msModelField')) {
    $fail('controller must not contain newQuery(msModelField');
}
if (str_contains($controllerSrc, 'newObject(msModelField')) {
    $fail('controller must not contain newObject(msModelField');
}

foreach (['create', 'update', 'delete'] as $method) {
    if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $fieldServiceSrc)) {
        $fail("ModelFieldService missing method {$method}");
    }
}

foreach (['createSection', 'updateSection', 'deleteSection'] as $method) {
    if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $sectionServiceSrc)) {
        $fail("ModelFieldSectionService missing method {$method}");
    }
}

if (!str_contains($registrySrc, "'ms3_model_field_service'")) {
    $fail('ServiceRegistry must register ms3_model_field_service');
}
if (!str_contains($registrySrc, "'ms3_model_field_section_service'")) {
    $fail('ServiceRegistry must register ms3_model_field_section_service');
}

if (!str_contains($controllerSrc, "services->get('ms3_model_field_service')")) {
    $fail('ModelFieldsController must resolve ms3_model_field_service from DI');
}
if (!str_contains($controllerSrc, "services->get('ms3_model_field_section_service')")) {
    $fail('ModelFieldsController must resolve ms3_model_field_section_service from DI');
}

if (str_contains($fieldServiceSrc, 'HttpStatus') || str_contains($sectionServiceSrc, 'HttpStatus')) {
    $fail('ModelField services must not import Router\\HttpStatus (HTTP stays in controller)');
}

// Envelope mapping: domain error codes → HTTP status (no MODX bootstrap).
require_once $root . '/vendor/autoload.php';

use MiniShop3\Controllers\Api\Manager\ModelFieldsController;
use MiniShop3\Router\HttpStatus;

$controllerReflection = new \ReflectionClass(ModelFieldsController::class);
$mapResult = $controllerReflection->getMethod('mapResult');
$mapResult->setAccessible(true);
$controller = $controllerReflection->newInstanceWithoutConstructor();

$notFound = $mapResult->invoke($controller, [
    'success' => false,
    'message' => 'Field not found',
    'error' => 'not_found',
]);
if (($notFound['success'] ?? true) !== false || (int)($notFound['code'] ?? 0) !== HttpStatus::NOT_FOUND) {
    $fail('mapResult must map error=not_found to HTTP 404');
}

$created = $mapResult->invoke($controller, [
    'success' => true,
    'data' => ['id' => 1],
    'message' => 'Field created successfully',
    'created' => true,
]);
if (($created['success'] ?? false) !== true || !isset($created['data']['id'])) {
    $fail('mapResult must unwrap successful create data');
}

fwrite(STDOUT, "OK ModelFieldsServiceStructureTest (controller LOC={$controllerLines})\n");
exit(0);
