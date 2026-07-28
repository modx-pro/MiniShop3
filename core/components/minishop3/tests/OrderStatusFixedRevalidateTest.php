<?php

/**
 * Guards fixed/final status re-validation after plugin mutation (issue #382).
 *
 * Run: php tests/OrderStatusFixedRevalidateTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$source = file_get_contents(dirname(__DIR__) . '/src/Services/Order/OrderStatusService.php');
if ($source === false) {
    $fail('cannot read OrderStatusService.php');
}

if (!str_contains($source, 'function validateStatusTransition')) {
    $fail('OrderStatusService must define validateStatusTransition');
}

if (!preg_match('/function validateStatusTransition[\s\S]*ms3_err_status_fixed/s', $source)) {
    $fail('validateStatusTransition must enforce fixed status position rule');
}

if (!preg_match(
    '/\$resolvedStatusId\s*!==\s*\$statusId[\s\S]*validateStatusTransition\s*\(\s*\$oldStatus\s*,\s*\$status\s*\)/s',
    $source
)) {
    $fail('OrderStatusService must re-run validateStatusTransition after plugin status mutation');
}

fwrite(STDOUT, "OK OrderStatusFixedRevalidateTest\n");
exit(0);
