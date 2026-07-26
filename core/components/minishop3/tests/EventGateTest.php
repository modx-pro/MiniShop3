<?php

/**
 * Static checks for EventGate returnedValues helpers (without MODX).
 *
 * Run: php tests/EventGateTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Utils\EventGate;

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
    ['a' => 1, 'b' => 2],
    EventGate::mergeReturnedValues(['a' => 1], ['b' => 2]),
    'mergeReturnedValues shallow merge'
);

$assertSame(
    ['a' => 1],
    EventGate::mergeReturnedValues(['a' => 1], []),
    'mergeReturnedValues empty returnedValues'
);

$assertSame(
    ['foo' => 'bar', 'price' => 99],
    EventGate::applyReturnedArray(
        ['foo' => 'bar', 'price' => 10],
        ['data' => ['price' => 99]],
        'data'
    ),
    'applyReturnedArray associative patch'
);

$assertSame(
    ['one', 'two'],
    EventGate::applyReturnedArray(
        ['old'],
        ['data' => ['one', 'two']],
        'data'
    ),
    'applyReturnedArray list replacement'
);

$assertSame(
    ['foo' => 'bar'],
    EventGate::applyReturnedArray(['foo' => 'bar'], ['other' => ['x' => 1]], 'data'),
    'applyReturnedArray missing channel'
);

$assertSame(true, EventGate::isCancelled([false]), 'isCancelled false value');
$assertSame(true, EventGate::isCancelled(['cancel']), 'isCancelled cancel string');
$assertSame(false, EventGate::isCancelled(['ok', 'fine']), 'isCancelled non-cancel responses');
$assertSame(false, EventGate::isCancelled('error'), 'isCancelled non-array response');

$assertSame('', EventGate::normalizeMessage([]), 'normalizeMessage empty array');
$assertSame('a<br/>b', EventGate::normalizeMessage(['a', 'b']), 'normalizeMessage array glue');
$assertSame('single', EventGate::normalizeMessage('single'), 'normalizeMessage string');
$assertSame('a<br/>b', EventGate::normalizeMessage(['a', '', 'b']), 'normalizeMessage filters empty parts');

$result = EventGate::buildInvokeResult(['x' => 1], ['y' => 2], '');
$assertSame(true, $result['success'], 'buildInvokeResult success when message empty');
$assertSame(['x' => 1, 'y' => 2], $result['data'], 'buildInvokeResult merged data');
$assertSame(['y' => 2], $result['values'], 'buildInvokeResult values key');

$result = EventGate::buildInvokeResult(['x' => 1], [], 'blocked');
$assertSame(false, $result['success'], 'buildInvokeResult failure when message set');

$result = EventGate::buildInvokeResult(['x' => 1], [], '0');
$assertSame(true, $result['success'], 'buildInvokeResult success matches legacy empty() check');

fwrite(STDOUT, "OK EventGateTest\n");
exit(0);
