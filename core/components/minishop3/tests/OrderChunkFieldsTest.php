<?php

/**
 * #828: ms3_get_order chunk must read real order/address fields, and the
 * snippet must supply a translated status name and escape user input.
 *
 * Run: php tests/OrderChunkFieldsTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$snippet = (string) file_get_contents($root . '/core/components/minishop3/elements/snippets/ms3_get_order.php');
foreach ([
    "load('minishop3:manager')",
    "getOne('Status')",
    "['status_name']",
] as $needle) {
    if (!str_contains($snippet, $needle)) {
        $fail("Snippet must provide translated status name: {$needle} (#828)");
    }
}

$chunk = (string) file_get_contents($root . '/core/components/minishop3/elements/chunks/ms3_get_order.tpl');
if (str_contains($chunk, '$order.Status')) {
    $fail('Chunk must not read the missing Status relation (#828)');
}
if (!str_contains($chunk, '$order.status_name')) {
    $fail('Chunk must render the supplied status_name (#828)');
}
if (str_contains($chunk, '$address.receiver')) {
    $fail('Chunk must not use the nonexistent receiver field (#828)');
}
if (preg_match('/>\{\$address\.(first_name|last_name)\}/', $chunk)) {
    $fail('Receiver names must be escaped (#828)');
}
if (str_contains($chunk, '{$order.order_comment}') || str_contains($chunk, '{$address.comment}')) {
    $fail('Comments must be escaped (#828)');
}
if (!str_contains($chunk, '$address.comment | e') || !str_contains($chunk, '$order.order_comment | e')) {
    $fail('Both comment fields must be rendered escaped (#828/#879)');
}

fwrite(STDOUT, "OK OrderChunkFieldsTest (#828)\n");
exit(0);
