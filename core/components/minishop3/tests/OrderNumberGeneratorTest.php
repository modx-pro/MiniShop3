<?php

/**
 * Static checks for order number parsing/building (#380).
 *
 * Run: php tests/OrderNumberGeneratorTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Order\OrderNumberGenerator;

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
    ['', '/', 0, 'empty num'],
    ['2501', '/', 0, 'missing separator'],
    ['2501/1', '/', 1, 'first counter'],
    ['2501/12', '/', 12, 'two-digit counter'],
    ['2501-3', '-', 3, 'dash separator'],
] as [$num, $separator, $expected, $label]) {
    $assertSame($expected, OrderNumberGenerator::parseCounter($num, $separator), $label);
}

foreach ([
    ['2501', '/', 1, '2501/1', 'build slash'],
    ['2501', '-', 10, '2501-10', 'build dash'],
] as [$prefix, $separator, $count, $expected, $label]) {
    $assertSame($expected, OrderNumberGenerator::buildNumber($prefix, $separator, $count), $label);
}

fwrite(STDOUT, "OK: OrderNumberGeneratorTest\n");
