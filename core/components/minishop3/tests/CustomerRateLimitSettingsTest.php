<?php

/**
 * #902: customer auth rate limits come from dedicated settings, not from the
 * account lockout duration, and cannot be disabled by a zero value.
 *
 * Run: php tests/CustomerRateLimitSettingsTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$read = static function (string $path) use ($fail): string {
    if (!is_readable($path)) {
        $fail("Missing {$path}");
    }

    return (string) file_get_contents($path);
};

$settings = require $root . '/_build/elements/settings.php';
foreach (['ms3_customer_login_rate_limit_attempts', 'ms3_customer_login_rate_limit_window'] as $key) {
    if (!isset($settings[$key]['value']) || (int) $settings[$key]['value'] < 1) {
        $fail("Missing or invalid installer default for {$key} (#902)");
    }
    foreach (['en', 'ru'] as $lang) {
        $lexicon = $read($root . "/core/components/minishop3/lexicon/{$lang}/setting.inc.php");
        if (!str_contains($lexicon, "setting_{$key}") || !str_contains($lexicon, "setting_{$key}_desc")) {
            $fail("{$lang} lexicon misses setting_{$key} (#902)");
        }
    }
}

foreach ([
    'src/Processors/Api/Customer/Login.php',
    'src/Processors/Api/Customer/Register.php',
    'src/Processors/Api/Customer/ForgotPassword.php',
    'src/Processors/Api/Customer/ResetPassword.php',
] as $relative) {
    $source = $read($root . '/core/components/minishop3/' . $relative);
    if (!str_contains($source, "ms3_customer_login_rate_limit_attempts")
        || !str_contains($source, "ms3_customer_login_rate_limit_window")) {
        $fail($relative . ' must use the dedicated rate-limit settings (#902)');
    }
    if (!str_contains($source, 'max(1,')) {
        $fail($relative . ' must guard against zero/negative setting values (#902)');
    }
}

$login = $read($root . '/core/components/minishop3/src/Processors/Api/Customer/Login.php');
if (str_contains($login, "getOption('ms3_customer_block_duration'")) {
    $fail('Login must not reuse the account lockout duration as an IP rate-limit window (#902)');
}

fwrite(STDOUT, "OK CustomerRateLimitSettingsTest (#902)\n");
exit(0);
