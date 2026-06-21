<?php

/**
 * Static checks for CSV option value parsing (without MODX).
 *
 * Run: php tests/UtilsParseImportedOptionValueTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Utils\Utils;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame(
    ['flour', 'water', 'salt'],
    Utils::parseImportedOptionValue('flour, water, salt', true),
    'multi comma-separated value'
);

$assertSame(
    ['flour', 'water'],
    Utils::parseImportedOptionValue('["flour","water"]', true),
    'multi JSON array value'
);

$assertSame(
    'flour, water',
    Utils::parseImportedOptionValue('flour, water', false),
    'single scalar with comma'
);

$assertSame(
    [],
    Utils::parseImportedOptionValue('', true),
    'empty multi value'
);

$assertSame(
    '',
    Utils::parseImportedOptionValue('', false),
    'empty single value'
);

fwrite(STDOUT, "OK UtilsParseImportedOptionValueTest\n");
exit(0);
