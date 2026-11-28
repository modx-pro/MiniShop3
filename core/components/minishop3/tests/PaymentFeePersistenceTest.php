<?php

/**
 * #897: the payment surcharge must have an authoritative store so provider
 * failures cannot silently zero it or invent a fee from clamped totals.
 *
 * Run: php tests/PaymentFeePersistenceTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$path = $root . '/core/components/minishop3/src/Services/Order/ManagerOrderCostRecalculator.php';
$source = (string) file_get_contents($path);

foreach (['persistedPaymentFee(', 'rememberPaymentFee(', "'payment_fee'"] as $needle) {
    if (!str_contains($source, $needle)) {
        $fail("Cost recalculator must use an authoritative payment_fee store: {$needle} (#897)");
    }
}
if (!str_contains($source, '$cost < $cartCost + $deliveryCost')) {
    $fail('Residual fee must be rejected for clamped/stale totals (#897)');
}
if (substr_count($source, "? \$this->persistedPaymentFee(\$order)") < 1) {
    $fail('Missing provider must preserve the persisted fee in force mode (#897)');
}

fwrite(STDOUT, "OK PaymentFeePersistenceTest (#897)\n");
exit(0);
