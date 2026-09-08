<?php

/**
 * Static checks for shared CatalogQuery helpers.
 *
 * Run: php tests/CatalogQueryTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Catalog\CatalogContextException;
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

$expectContextException = static function (callable $fn, string $case) use ($fail): void {
    try {
        $fn();
        $fail($case . ': expected CatalogContextException');
    } catch (CatalogContextException $e) {
        if ($e->getLexiconKey() !== CatalogContextException::LEXICON_INVALID) {
            $fail($case . ': unexpected lexicon key ' . $e->getLexiconKey());
        }
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
$assertSame('web', CatalogQuery::resolveContext([], 'mgr'), 'invalid fallback mgr → web');
$assertSame('shop', CatalogQuery::resolveContext(['context' => 'shop'], 'web'), 'valid explicit context');
$assertSame('shop-v2', CatalogQuery::resolveContext(['context' => 'shop-v2'], 'web'), 'valid charset with dash/underscore');

$expectContextException(
    static fn () => CatalogQuery::resolveContext(['context' => 'bad chars!'], 'web'),
    'invalid charset'
);
$expectContextException(
    static fn () => CatalogQuery::resolveContext(['context' => str_repeat('a', 101)], 'web'),
    'length > 100'
);
$expectContextException(
    static fn () => CatalogQuery::resolveContext(['context' => 'mgr'], 'web'),
    'mgr prefix lowercase'
);
$expectContextException(
    static fn () => CatalogQuery::resolveContext(['context' => 'Mgr'], 'web'),
    'mgr prefix mixed case'
);
$expectContextException(
    static fn () => CatalogQuery::resolveContext(['context' => 'mgrCustom'], 'web'),
    'mgr prefix prefix'
);
$expectContextException(
    static fn () => CatalogQuery::resolveContext(['context' => ['web']], 'web'),
    'array context'
);
$expectContextException(
    static fn () => CatalogQuery::resolveContext(['context' => true], 'web'),
    'bool context'
);

$assertSame(null, CatalogQuery::sanitizeContext(null), 'sanitize null → absent');
$assertSame(null, CatalogQuery::sanitizeContext(''), 'sanitize empty → absent');
$assertSame(null, CatalogQuery::sanitizeContext('   '), 'sanitize whitespace → absent');
$assertSame('web', CatalogQuery::sanitizeContext(' web '), 'sanitize valid trim');

$expectContextException(
    static fn () => CatalogQuery::sanitizeContext('mgr'),
    'sanitize throws on mgr'
);

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
