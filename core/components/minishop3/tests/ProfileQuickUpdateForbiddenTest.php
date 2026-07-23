<?php

/**
 * Regression #413: customer/add must not write GDPR consent timestamp.
 *
 * Run: php tests/ProfileQuickUpdateForbiddenTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$src = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/CustomerProfileController.php');
if ($src === false || $src === '') {
    $fail('unable to read CustomerProfileController.php');
}

if (
    !preg_match(
        '/private const PROFILE_QUICK_UPDATE_FORBIDDEN\s*=\s*\[(.*?)\];/s',
        $src,
        $match
    )
) {
    $fail('PROFILE_QUICK_UPDATE_FORBIDDEN constant not found');
}

$forbiddenBlock = $match[1];
if (!preg_match_all("/'([^']+)'/", $forbiddenBlock, $keys)) {
    $fail('no forbidden field keys parsed');
}

$forbidden = $keys[1];
foreach (['privacy_accepted_at', 'privacy_ip', 'password', 'token', 'email_verified_at'] as $key) {
    if (!in_array($key, $forbidden, true)) {
        $fail("PROFILE_QUICK_UPDATE_FORBIDDEN must include {$key}");
    }
}

if (!str_contains($src, 'in_array($key, self::PROFILE_QUICK_UPDATE_FORBIDDEN, true)')) {
    $fail('updateField must enforce PROFILE_QUICK_UPDATE_FORBIDDEN');
}

fwrite(STDOUT, "OK ProfileQuickUpdateForbiddenTest\n");
exit(0);
