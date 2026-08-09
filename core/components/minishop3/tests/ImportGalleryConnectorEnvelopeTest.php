<?php

/**
 * Import/gallery manager HTTP boundary must use Response envelope (#341 / #419).
 *
 * Processors\Api\Index unwraps Response as:
 *   $this->success('', $responseData['data'] ?? $responseData);
 * Raw getResponse() → no `data` key → nested object.object.*.
 *
 * Run: php tests/ImportGalleryConnectorEnvelopeTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Router\Response;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$processor = new class {
    public function isError(): bool
    {
        return false;
    }

    public function getMessage(): string
    {
        return 'scheduled';
    }

    public function getObject(): array
    {
        return [
            'import_id' => 'imp-1',
            'scheduled' => true,
        ];
    }
};

$fixedData = Response::fromProcessor($processor)->getData();
$fixedObject = $fixedData['data'] ?? $fixedData;

if (!is_array($fixedObject) || ($fixedObject['scheduled'] ?? null) !== true) {
    $fail('fromProcessor + Index unwrap must yield connector object with scheduled=true');
}
if (($fixedObject['import_id'] ?? null) !== 'imp-1') {
    $fail('expected connector object.import_id === imp-1');
}
if (isset($fixedObject['object'])) {
    $fail('connector object must not nest another processor object key');
}
if (($fixedData['message'] ?? null) !== 'scheduled') {
    $fail('processor message must survive in API Response');
}

$errorProcessor = new class {
    public function isError(): bool
    {
        return true;
    }

    public function getMessage(): string
    {
        return 'import failed';
    }

    public function getObject(): array
    {
        return [
            'import_id' => 'imp-err',
            'errors' => 3,
        ];
    }
};

$error = Response::fromProcessor($errorProcessor);
$errorData = $error->getData();
if ($error->getStatusCode() !== 400) {
    $fail('fromProcessor error must use HTTP 400');
}
if (($errorData['success'] ?? true) !== false || ($errorData['message'] ?? null) !== 'import failed') {
    $fail('fromProcessor error must keep success=false and processor message');
}
if (($errorData['data']['import_id'] ?? null) !== 'imp-err') {
    $fail('fromProcessor error must keep processor object under data');
}
if (($errorData['data']['errors'] ?? null) !== 3) {
    $fail('fromProcessor error must keep import error count in data.errors');
}

$routeFiles = [
    __DIR__ . '/../config/routes/manager.php',
    __DIR__ . '/../config/routes/web.php',
];

foreach ($routeFiles as $routeFile) {
    $contents = file_get_contents($routeFile);
    if ($contents === false) {
        $fail('unable to read ' . $routeFile);
    }
    if (str_contains($contents, '->getResponse()')) {
        $fail(basename($routeFile) . ' must not return raw processor getResponse()');
    }
}

$trait = file_get_contents(
    __DIR__ . '/../src/Controllers/Api/Manager/Concerns/RunsMs3Processors.php'
);
if ($trait === false || !str_contains($trait, 'Response::fromProcessor(')) {
    $fail('RunsMs3Processors must map processors via Response::fromProcessor');
}

foreach ([
    'ImportController.php',
    'UtilitiesGalleryController.php',
] as $controllerFile) {
    $path = __DIR__ . '/../src/Controllers/Api/Manager/' . $controllerFile;
    $src = file_get_contents($path);
    if ($src === false) {
        $fail('missing controller ' . $controllerFile);
    }
    if (!str_contains($src, 'RunsMs3Processors')) {
        $fail($controllerFile . ' must use RunsMs3Processors');
    }
}

$managerRoutes = file_get_contents(__DIR__ . '/../config/routes/manager.php');
if ($managerRoutes === false) {
    $fail('unable to read manager.php');
}
if (!str_contains($managerRoutes, 'UtilitiesGalleryController')) {
    $fail('gallery routes must dispatch UtilitiesGalleryController');
}
if (!str_contains($managerRoutes, 'ImportController')) {
    $fail('import routes must dispatch ImportController');
}

fwrite(STDOUT, "OK ImportGalleryConnectorEnvelopeTest\n");
exit(0);
