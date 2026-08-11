<?php

/**
 * Behavioral checks for ProductLinkService link-type rules (without MODX/MySQL).
 *
 * Run: php tests/ProductLinkServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Product\ProductLinkService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL ProductLinkServiceTest: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $cond, string $case) use ($fail): void {
    if (!$cond) {
        $fail($case);
    }
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertPairs = static function (array $expected, ?array $actual, string $case) use ($assertSame): void {
    $assertSame($expected, $actual, $case);
};

foreach (['one_to_many', 'many_to_one', 'one_to_one', 'many_to_many'] as $type) {
    $assertTrue(ProductLinkService::supportsLinkType($type), "supports {$type}");
}

$assertTrue(!ProductLinkService::supportsLinkType('invalid'), 'rejects unknown type');

$assertPairs(
    [['master' => 1, 'slave' => 2]],
    ProductLinkService::initialPairsForType('one_to_many', 1, 2),
    'one_to_many pair'
);

$assertPairs(
    [['master' => 2, 'slave' => 1]],
    ProductLinkService::initialPairsForType('many_to_one', 1, 2),
    'many_to_one stores slave as master'
);

$bidirectional = [
    ['master' => 1, 'slave' => 2],
    ['master' => 2, 'slave' => 1],
];

$assertPairs(
    $bidirectional,
    ProductLinkService::initialPairsForType('one_to_one', 1, 2),
    'one_to_one bidirectional'
);

$assertPairs(
    $bidirectional,
    ProductLinkService::initialPairsForType('many_to_many', 1, 2),
    'many_to_many initial bidirectional before mesh'
);

$assertSame(null, ProductLinkService::initialPairsForType('unknown', 1, 2), 'unknown type → null');

$assertSame(true, ProductLinkService::belongsToProduct(5, 5, 9), 'scope: path product is master');
$assertSame(true, ProductLinkService::belongsToProduct(5, 9, 5), 'scope: path product is slave');
$assertSame(false, ProductLinkService::belongsToProduct(5, 8, 9), 'scope: unrelated products');

fwrite(STDOUT, "OK ProductLinkServiceTest\n");
exit(0);
