<?php

/**
 * CategoryProductsController IDOR guard: publish/multiple scoped by category parent (#418).
 *
 * Run: php tests/CategoryProductsControllerScopeTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/StubMsProduct.php';
require __DIR__ . '/stubs/StubMsCategory.php';
require __DIR__ . '/stubs/CategoryProductScopeModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Api\Manager\CategoryProductsController;
use MiniShop3\Model\msProduct;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Tests\Stubs\CategoryProductScopeModxStub;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$modx = new CategoryProductScopeModxStub();
$modx->setPermissions(['msproduct_publish', 'msproduct_delete', 'msproduct_save']);
$modx->products = [
    ['id' => 999, 'parent' => 2, 'published' => 0],
    ['id' => 100, 'parent' => 1, 'published' => 0],
    ['id' => 101, 'parent' => 2, 'published' => 0],
    ['id' => 200, 'parent' => 1, 'published' => 0, 'policies' => ['save' => false]],
];
$modx->categories = [
    ['id' => 2, 'parent' => 1],
];

$controller = new CategoryProductsController($modx);

// publish: foreign product under category 1 → 404 (same as not found)
$foreignPublish = $controller->publish([
    'id' => 1,
    'productId' => 999,
    'published' => 1,
]);
$assertSame(false, $foreignPublish['success'] ?? null, 'foreign publish success');
$assertSame(HttpStatus::NOT_FOUND, $foreignPublish['code'] ?? null, 'foreign publish code');

$productCalls = array_values(array_filter(
    $modx->getObjectCalls,
    static fn(array $call): bool => ($call['class'] ?? '') === msProduct::class
        && is_array($call['criteria'] ?? null)
));
$assertSame(['id' => 999, 'parent' => 1], $productCalls[0]['criteria'] ?? null, 'publish scope criteria');

// publish: missing category id
$noCategory = $controller->publish(['productId' => 999, 'published' => 1]);
$assertSame(HttpStatus::BAD_REQUEST, $noCategory['code'] ?? null, 'publish missing category');

// multiple: all foreign ids → no mutation, error response
$allForeign = $controller->multiple([
    'id' => 1,
    'method' => 'delete',
    'ids' => [999],
]);
$assertSame(false, $allForeign['success'] ?? null, 'all foreign multiple success');
$assertSame(HttpStatus::INTERNAL_SERVER_ERROR, $allForeign['code'] ?? null, 'all foreign multiple code');

// multiple: missing category
$noCatMultiple = $controller->multiple(['method' => 'delete', 'ids' => [999]]);
$assertSame(HttpStatus::BAD_REQUEST, $noCatMultiple['code'] ?? null, 'multiple missing category');

// bulkDelete delegates to multiple with same scope
$bulkDelete = $controller->bulkDelete(['id' => 1, 'ids' => [999]]);
$assertSame(false, $bulkDelete['success'] ?? null, 'bulkDelete all foreign');

// sort happy path: in-category product reordered
$modx->getObjectCalls = [];
$sort = $controller->sort([
    'id' => 1,
    'items' => [['id' => 100, 'menuindex' => 5]],
]);
$assertSame(true, $sort['success'] ?? null, 'sort success');
$assertSame(1, $sort['data']['updated'] ?? null, 'sort updated count');

// updateProductData: in-scope product with document save policy denied → 403 (#473 pattern)
$modx->getObjectCalls = [];
$aclDenied = $controller->updateProductData([
    'id' => 1,
    'productId' => 200,
    'pagetitle' => 'updated title',
]);
$assertSame(false, $aclDenied['success'] ?? null, 'updateProductData ACL denied success');
$assertSame(HttpStatus::FORBIDDEN, $aclDenied['code'] ?? null, 'updateProductData ACL denied code');
$assertSame(
    'Save permission denied for this document',
    $aclDenied['message'] ?? null,
    'updateProductData ACL denied message'
);

// updateProductData: out-of-scope product → 403 (scope guard still enforced)
$outOfScope = $controller->updateProductData([
    'id' => 1,
    'productId' => 999,
    'pagetitle' => 'updated title',
]);
$assertSame(false, $outOfScope['success'] ?? null, 'updateProductData out-of-scope success');
$assertSame(HttpStatus::FORBIDDEN, $outOfScope['code'] ?? null, 'updateProductData out-of-scope code');

fwrite(STDOUT, "OK: CategoryProductsControllerScopeTest\n");
