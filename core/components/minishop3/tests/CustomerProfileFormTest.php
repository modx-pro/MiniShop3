<?php

/**
 * #880: the customer profile form must carry ms3_action as a hidden field.
 * Submit-button name/value pairs are excluded from `new FormData(form)`.
 *
 * Run: php tests/CustomerProfileFormTest.php
 */

declare(strict_types=1);

$path = dirname(__DIR__, 4) . '/core/components/minishop3/elements/chunks/ms3_customer.tpl';
$source = (string) file_get_contents($path);

if (!preg_match('/<input[^>]+type="hidden"[^>]+name="ms3_action"[^>]+value="customer\/update-profile"/', $source)) {
    fwrite(STDERR, "FAIL: ms3_action must be a hidden input with customer/update-profile (#880)\n");
    exit(1);
}
if (preg_match('/<button[^>]+name="ms3_action"/', $source)) {
    fwrite(STDERR, "FAIL: submit button must not carry ms3_action (#880)\n");
    exit(1);
}

fwrite(STDOUT, "OK CustomerProfileFormTest (#880)\n");
exit(0);
