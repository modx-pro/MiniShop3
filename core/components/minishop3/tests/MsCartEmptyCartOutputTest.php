<?php

/**
 * msCart must apply toPlaceholder/showLog/return on every path (#803).
 *
 * The empty-cart branches used to return the chunk (or data) directly, so an
 * empty cart never reached the output tail: the placeholder stayed empty and
 * the chunk HTML leaked into the call site — the default state for every new
 * visitor. All paths now go through one shared output closure.
 *
 * Run: php tests/MsCartEmptyCartOutputTest.php
 */

declare(strict_types=1);

$snippetPath = __DIR__ . '/../elements/snippets/ms3_cart.php';
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL MsCartEmptyCartOutputTest: {$message}\n");
    exit(1);
};

if (!is_readable($snippetPath)) {
    $fail("Cannot read {$snippetPath}");
}

$source = (string)file_get_contents($snippetPath);

// No path may return the chunk or the data array directly — the shared
// closure is the only place that renders and applies the output parameters.
if (str_contains($source, 'return $pdoFetch->getChunk(')) {
    $fail('no path may return the chunk directly: toPlaceholder/showLog would be bypassed (#803)');
}
if (str_contains($source, 'return compact(')) {
    $fail('no path may return the data array directly: it must go through the shared output tail (#803)');
}

// The output parameters are applied exactly once, in the shared tail.
if (substr_count($source, '$pdoFetch->getChunk(') !== 1) {
    $fail('the chunk must be rendered exactly once, in the shared output tail (#803)');
}
if (substr_count($source, 'setPlaceholder($toPlaceholder') !== 1) {
    $fail('toPlaceholder must be applied exactly once, in the shared output tail (#803)');
}
if (substr_count($source, 'msCartLog') !== 1) {
    $fail('showLog must be appended exactly once, in the shared output tail (#803)');
}

// The empty-cart branch skips the query but routes through the shared tail.
if (!preg_match(
    '/if \(empty\(\$status\[\'total_count\'\]\) \|\| empty\(\$cart\)\) \{[^}]*return \$renderOutput\(compact\(\'total\', \'products\', \'status\'\)\);/s',
    $source
)) {
    $fail('the empty-cart branch must return through the shared output closure (#803)');
}

// The full-cart path uses the same closure.
if (!str_contains($source, 'return $renderOutput($outputData);')) {
    $fail('the full-cart path must return through the shared output closure (#803)');
}

fwrite(STDOUT, "OK MsCartEmptyCartOutputTest\n");
exit(0);
