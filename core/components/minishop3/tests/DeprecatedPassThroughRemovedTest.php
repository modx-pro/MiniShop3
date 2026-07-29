<?php

/**
 * Regression #357: remove deprecated pass-through wrappers in core.
 *
 * Run: php tests/DeprecatedPassThroughRemovedTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$root = dirname(__DIR__);

$checks = [
    'src/Services/Order/OrderService.php' => [
        'function handleOrderSave',
        'function removeOrder',
    ],
    'src/Controllers/Customer/Customer.php' => [
        'function getId',
    ],
];

foreach ($checks as $relativePath => $forbiddenSnippets) {
    $path = $root . '/' . $relativePath;
    $src = file_get_contents($path);
    if ($src === false || $src === '') {
        $fail("unable to read {$relativePath}");
    }

    foreach ($forbiddenSnippets as $snippet) {
        if (str_contains($src, $snippet)) {
            $fail("{$relativePath} must not contain deprecated pass-through {$snippet}");
        }
    }
}

$coreSrc = $root . '/src';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($coreSrc, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relative = str_replace($root . '/', '', $path);
    $src = file_get_contents($path);
    if ($src === false) {
        $fail("unable to read {$relative}");
    }

    if (str_contains($src, 'handleOrderSave(')) {
        $fail("core must not call handleOrderSave(); use msOrder::save() (found in {$relative})");
    }

    if (str_contains($src, 'removeOrder(')) {
        $fail("core must not call removeOrder(); use msOrder::remove() (found in {$relative})");
    }

    if (
        str_contains($relative, 'Controllers/Customer/Customer.php')
        || str_contains($relative, 'Services/Customer/RegisterService.php')
    ) {
        continue;
    }

    if (preg_match('/\$this->ms3->customer->getId\s*\(/', $src)) {
        $fail("core must not call customer->getId(); use getOrCreate() (found in {$relative})");
    }
}

fwrite(STDOUT, "OK DeprecatedPassThroughRemovedTest\n");
exit(0);
