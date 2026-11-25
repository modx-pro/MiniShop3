<?php

/**
 * #862: the order snippet's profile sync must use the shared blank check,
 * so zero values survive in both sync modes.
 *
 * Standalone (no MODX). Run: php tests/OrderProfileZeroContractTest.php
 */

declare(strict_types=1);

$path = dirname(__DIR__) . '/elements/snippets/ms3_order.php';
$source = (string) file_get_contents($path);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL OrderProfileZeroContractTest: {$message}\n");
    exit(1);
};

if (!str_contains($source, 'use MiniShop3\\Utils\\ValuePresence;')) {
    $fail('ms3_order must import ValuePresence');
}

if (substr_count($source, 'ValuePresence::isBlank') < 2) {
    $fail('both sync branches must use the shared blank check');
}

if (str_contains($source, '!empty($customerData[$customerField])')) {
    $fail('customer profile values must not be filtered with empty()');
}

if (str_contains($source, 'if (!empty($value))')) {
    $fail('resolved profile values must not be filtered with empty()');
}

fwrite(STDOUT, "OK OrderProfileZeroContractTest\n");
exit(0);
