<?php

/**
 * Static checks for shared CatalogQuery helpers.
 *
 * Run: php tests/CatalogQueryTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Catalog\CatalogQuery;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame(20, CatalogQuery::resolveLimit([]), 'default limit');
$assertSame(100, CatalogQuery::resolveLimit(['limit' => 500]), 'limit cap');
$assertSame(40, CatalogQuery::resolveOffset(['page' => 3], 20), 'page offset');
$assertSame(5, CatalogQuery::resolveDepth([]), 'default depth');
$assertSame(10, CatalogQuery::resolveDepth(['depth' => 99]), 'depth cap');
$assertSame('web', CatalogQuery::resolveContext([], 'web'), 'context fallback');
$assertSame('shop', CatalogQuery::resolveContext(['context' => ' shop '], 'web'), 'context trim');
$assertSame('web', CatalogQuery::resolveContext(['context' => ''], 'web'), 'empty context → fallback');
$assertSame('web', CatalogQuery::resolveContext(['context' => '   '], 'web'), 'whitespace context → fallback');
$assertSame('web', CatalogQuery::resolveContext([], ''), 'empty fallback → web');

$map = [
    'id' => 't.id',
    'menuindex' => 't.menuindex',
];
[$field, $dir] = CatalogQuery::resolveSort(['sort' => 'id', 'dir' => 'desc'], $map);
$assertSame('t.id', $field, 'sort field');
$assertSame('DESC', $dir, 'sort dir');

[$field, $dir] = CatalogQuery::resolveSort(['sort' => 'nope'], $map);
$assertSame('t.menuindex', $field, 'unknown sort → default');
$assertSame('ASC', $dir, 'default dir');

$assertSame(true, CatalogQuery::toBool('yes'), 'toBool yes');
$assertSame(false, CatalogQuery::toBool('0'), 'toBool 0');

fwrite(STDOUT, "OK CatalogQueryTest\n");
exit(0);
