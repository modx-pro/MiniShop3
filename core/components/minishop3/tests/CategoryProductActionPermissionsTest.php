<?php

/**
 * Permission map for category product bulk actions (#378).
 *
 * Run: php tests/CategoryProductActionPermissionsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Category\CategoryProductActionPermissions;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

foreach ([
    ['publish', 'msproduct_publish'],
    ['unpublish', 'msproduct_publish'],
    ['delete', 'msproduct_delete'],
    ['undelete', 'msproduct_delete'],
    ['show', 'msproduct_save'],
    ['hide', 'msproduct_save'],
    ['unknown', null],
    ['', null],
] as [$method, $expected]) {
    $assertSame(
        $expected,
        CategoryProductActionPermissions::forMethod($method),
        "forMethod({$method})"
    );
}

$mutation = CategoryProductActionPermissions::mutationPermissions();
sort($mutation);
$assertSame(
    ['msproduct_delete', 'msproduct_publish', 'msproduct_save'],
    $mutation,
    'mutationPermissions'
);

fwrite(STDOUT, "OK: CategoryProductActionPermissionsTest\n");
