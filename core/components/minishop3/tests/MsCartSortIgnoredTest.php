<?php

/**
 * msCart must not pretend to sort products (#800).
 *
 * Cart output order comes from the cart itself: query rows are re-keyed by product
 * id and rendered by iterating $cart. &sortby/&sortdir used to be merged into the
 * pdoFetch config and silently lost, so the snippet dropped them with a warning
 * instead — a parameter that is accepted and does nothing is worse than none.
 *
 * Run: php tests/MsCartSortIgnoredTest.php
 */

declare(strict_types=1);

$snippetPath = __DIR__ . '/../elements/snippets/ms3_cart.php';
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL MsCartSortIgnoredTest: {$message}\n");
    exit(1);
};

if (!is_readable($snippetPath)) {
    $fail("Cannot read {$snippetPath}");
}

$source = (string)file_get_contents($snippetPath);

// The query config must not carry an ORDER BY anymore — output order is the cart's.
$defaultBlock = preg_match(
    '/\$default = \[(.*?)\n\];/s',
    $source,
    $match
);
if ($defaultBlock !== 1) {
    $fail('cannot read the $default pdoFetch config');
}
if (preg_match("/'(sortby|sortdir)' =>/", $match[1])) {
    $fail('the pdoFetch default config must not define sortby/sortdir (#800)');
}

// Both parameters must be dropped from $scriptProperties before it is used/cached.
if (!str_contains($source, "['sortby', 'sortdir']")) {
    $fail('msCart must list sortby and sortdir as ignored (#800)');
}
if (substr_count($source, 'unset($scriptProperties[$ignoredSortProperty]);') !== 1) {
    $fail('msCart must unset the ignored sort properties (#800)');
}

// The drop has to be visible in the log, otherwise it stays a silent no-op.
if (!str_contains($source, 'ms3_cart ignored ')) {
    $fail('msCart must log a warning about the ignored sort parameters (#800)');
}

if (!str_contains($source, 'LOG_LEVEL_WARN')) {
    $fail('the warning must use the MODX warn level');
}

// Cart order still wins: rows are re-keyed by product id and iterated over $cart.
if (!str_contains($source, "\$rows[\$row['id']] = \$row;")) {
    $fail('rows must stay keyed by product id — query order is irrelevant (#800)');
}
if (!str_contains($source, 'foreach ($cart as $key => $entry) {')) {
    $fail('output must still be built by iterating the cart (#800)');
}

fwrite(STDOUT, "OK MsCartSortIgnoredTest\n");
exit(0);