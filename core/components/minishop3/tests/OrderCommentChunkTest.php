<?php

/**
 * #879: customer-entered comments must be shown from real fields and escaped
 * in both order chunks.
 *
 * Run: php tests/OrderCommentChunkTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

foreach ([
    'ms3_customer_order_details.tpl',
    'ms3_get_order.tpl',
] as $name) {
    $path = $root . '/core/components/minishop3/elements/chunks/' . $name;
    if (!is_readable($path)) {
        $fail("Missing {$path}");
    }
    $source = (string) file_get_contents($path);

    if (str_contains($source, '$order.comment')) {
        $fail("{$name} still uses the nonexistent \$order.comment field (#879)");
    }
    if (!str_contains($source, '$order.order_comment')) {
        $fail("{$name} must render msOrder.order_comment (#879)");
    }
    if (!str_contains($source, '$address.comment')) {
        $fail("{$name} must render msOrderAddress.comment (#879)");
    }
    if (preg_match('/\{\$(address\.comment|order\.order_comment)\}/', $source)) {
        $fail("{$name} must escape customer comments with |e (#879)");
    }
}

fwrite(STDOUT, "OK OrderCommentChunkTest (#879)\n");
exit(0);
