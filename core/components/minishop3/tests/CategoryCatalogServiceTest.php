<?php

/**
 * Static checks for public category catalog helpers (without MODX).
 *
 * Run: php tests/CategoryCatalogServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Services\Category\CategoryCatalogService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame(5, CatalogQuery::resolveDepth([]), 'default depth');
$assertSame(5, CatalogQuery::resolveDepth(['depth' => 0]), 'invalid depth falls back');
$assertSame(3, CatalogQuery::resolveDepth(['depth' => 3]), 'custom depth');
$assertSame(10, CatalogQuery::resolveDepth(['depth' => 50]), 'depth capped at 10');

[$field, $dir] = CategoryCatalogService::resolveSort([]);
$assertSame('msCategory.menuindex', $field, 'default sort field');
$assertSame('ASC', $dir, 'default sort dir');

[$field, $dir] = CategoryCatalogService::resolveSort(['sort' => 'pagetitle', 'dir' => 'desc']);
$assertSame('msCategory.pagetitle', $field, 'pagetitle sort');
$assertSame('DESC', $dir, 'desc dir');

[$field, $dir] = CategoryCatalogService::resolveSort(['sort' => 'unknown', 'sortdir' => 'DESC']);
$assertSame('msCategory.menuindex', $field, 'unknown sort → menuindex');
$assertSame('DESC', $dir, 'sortdir alias');

$assertSame(
    [
        'id' => 12,
        'pagetitle' => 'Tea',
        'menutitle' => 'Tea',
        'alias' => 'tea',
        'uri' => 'catalog/tea/',
        'parent' => 5,
        'menuindex' => 2,
        'hidemenu' => false,
        'content' => '<p>x</p>',
    ],
    CategoryCatalogService::whitelistPublicPayload([
        'id' => 12,
        'pagetitle' => 'Tea',
        'menutitle' => 'Tea',
        'alias' => 'tea',
        'uri' => 'catalog/tea/',
        'parent' => 5,
        'menuindex' => 2,
        'hidemenu' => false,
        'content' => '<p>x</p>',
        'template' => 5,
        'internal' => 'leak',
    ], true),
    'whitelist keeps content, drops template'
);

$assertSame(
    [
        'id' => 12,
        'pagetitle' => 'Tea',
    ],
    CategoryCatalogService::whitelistPublicPayload([
        'id' => 12,
        'pagetitle' => 'Tea',
        'content' => '<p>hidden</p>',
        'template' => 5,
    ], false),
    'whitelist omits content when flag off'
);

$byId = [
    5 => ['id' => 5, 'pagetitle' => 'Catalog', 'parent' => 0],
    12 => ['id' => 12, 'pagetitle' => 'Tea', 'parent' => 5],
    13 => ['id' => 13, 'pagetitle' => 'Coffee', 'parent' => 5],
    20 => ['id' => 20, 'pagetitle' => 'Green', 'parent' => 12],
];
$childrenByParent = [
    0 => [5],
    5 => [12, 13],
    12 => [20],
];

$tree = CategoryCatalogService::buildTreeNodes($byId, $childrenByParent, 0, 2);
$assertSame(1, count($tree), 'root has one child');
$assertSame(5, $tree[0]['id'], 'root child is catalog');
$assertSame(2, count($tree[0]['children']), 'depth 2 includes tea+coffee');
$assertSame([], $tree[0]['children'][0]['children'], 'depth 2 does not include green');

$deep = CategoryCatalogService::buildTreeNodes($byId, $childrenByParent, 5, 2);
$assertSame(2, count($deep), 'subtree under catalog');
$assertSame(1, count($deep[0]['children']), 'tea has green at depth 2 from parent 5');
$assertSame(20, $deep[0]['children'][0]['id'], 'green leaf');

$empty = CategoryCatalogService::buildTreeNodes($byId, $childrenByParent, 0, 0);
$assertSame([], $empty, 'depth 0 → empty');

fwrite(STDOUT, "OK CategoryCatalogServiceTest\n");
exit(0);
