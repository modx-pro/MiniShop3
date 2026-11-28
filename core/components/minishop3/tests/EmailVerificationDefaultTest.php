<?php

/**
 * #868: installer default and all code fallbacks for email verification must
 * agree (false), so registration auto-login works without a stored setting.
 *
 * Run: php tests/EmailVerificationDefaultTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$settings = require $root . '/_build/elements/settings.php';
if (($settings['ms3_customer_require_email_verification']['value'] ?? null) !== false) {
    $fail('Installer default must be false (#868)');
}

foreach ([
    'src/Processors/Api/Customer/Register.php',
    'src/Services/Customer/RegisterService.php',
] as $relative) {
    $source = (string) file_get_contents($root . '/core/components/minishop3/' . $relative);
    if (!str_contains($source, "getOption('ms3_customer_require_email_verification', null, false)")) {
        $fail($relative . ' must fall back to false (#868)');
    }
}

fwrite(STDOUT, "OK EmailVerificationDefaultTest (#868)\n");
exit(0);
