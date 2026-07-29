<?php

/**
 * Permission map and authz evaluate() for category product bulk actions (#378).
 *
 * Covers 403/400 decisions used by CategoryProductsController::multiple()
 * without bootstrapping MODX.
 *
 * Run: php tests/CategoryProductActionPermissionsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Router\HttpStatus;
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

$denyAll = static fn(string $permission): bool => false;
$allowAll = static fn(string $permission): bool => true;
$allowOnly = static function (string $allowed) {
    return static fn(string $permission): bool => $permission === $allowed;
};

// Unknown method → 400 (contract change vs old silent loop → 500)
$unknown = CategoryProductActionPermissions::evaluate('not-a-method', $denyAll);
$assertSame(false, $unknown['allowed'], 'unknown allowed');
$assertSame(HttpStatus::BAD_REQUEST, $unknown['status'], 'unknown status');
$assertSame('unknown_method', $unknown['reason'], 'unknown reason');
$assertSame('ms3_err_unknown_method', $unknown['message'], 'unknown message');

// Missing permission → 403 before any product mutation
$forbidden = CategoryProductActionPermissions::evaluate('delete', $denyAll);
$assertSame(false, $forbidden['allowed'], 'forbidden allowed');
$assertSame(HttpStatus::FORBIDDEN, $forbidden['status'], 'forbidden status');
$assertSame('forbidden', $forbidden['reason'], 'forbidden reason');
$assertSame('msproduct_delete', $forbidden['permission'], 'forbidden permission');
$assertSame(
    'ms3_err_access_denied_permission',
    $forbidden['message'],
    'forbidden message'
);

// Wrong permission for method → still 403 (no escalation via method param)
$wrongPerm = CategoryProductActionPermissions::evaluate('publish', $allowOnly('msproduct_delete'));
$assertSame(false, $wrongPerm['allowed'], 'wrongPerm allowed');
$assertSame(HttpStatus::FORBIDDEN, $wrongPerm['status'], 'wrongPerm status');
$assertSame('msproduct_publish', $wrongPerm['permission'], 'wrongPerm permission');

// Authorized → ok (controller may proceed to load products)
$ok = CategoryProductActionPermissions::evaluate('delete', $allowOnly('msproduct_delete'));
$assertSame(true, $ok['allowed'], 'ok allowed');
$assertSame(HttpStatus::OK, $ok['status'], 'ok status');
$assertSame('ok', $ok['reason'], 'ok reason');

// bulkDelete path forces method=delete before evaluate (same 403 semantics)
$bulkMethod = 'delete';
$bulk = CategoryProductActionPermissions::evaluate($bulkMethod, $denyAll);
$assertSame(HttpStatus::FORBIDDEN, $bulk['status'], 'bulkDelete-equivalent status');
$assertSame('msproduct_delete', $bulk['permission'], 'bulkDelete-equivalent permission');

// publish() defense-in-depth uses the same forbidden shape for msproduct_publish
$publishDenied = CategoryProductActionPermissions::evaluate('publish', $denyAll);
$assertSame(HttpStatus::FORBIDDEN, $publishDenied['status'], 'publish denied status');
$assertSame('msproduct_publish', $publishDenied['permission'], 'publish denied permission');

$assertSame(true, CategoryProductActionPermissions::evaluate('show', $allowAll)['allowed'], 'show allowAll');

foreach ([
    ['publish', ['publish']],
    ['unpublish', ['save', 'unpublish']],
    ['delete', ['delete']],
    ['undelete', ['save', 'undelete']],
    ['show', ['save']],
    ['unknown', null],
] as [$method, $expected]) {
    $assertSame(
        $expected,
        CategoryProductActionPermissions::documentPoliciesForMethod($method),
        "documentPoliciesForMethod({$method})"
    );
}

fwrite(STDOUT, "OK: CategoryProductActionPermissionsTest\n");
