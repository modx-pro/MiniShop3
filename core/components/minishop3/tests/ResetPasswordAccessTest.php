<?php

/**
 * #755: ResetPassword must deny inactive/permanent block like ForgotPassword (same error as invalid token).
 *
 * Run: php tests/ResetPasswordAccessTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$src = file_get_contents(__DIR__ . '/../src/Processors/Api/Customer/ResetPassword.php');
if ($src === false) {
    $fail('cannot read ResetPassword.php');
}

$validatePos = strpos($src, 'validateToken($token, msCustomerToken::TYPE_PASSWORD_RESET)');
if ($validatePos === false) {
    $fail('ResetPassword must validate password-reset token');
}

$denyPos = strpos($src, 'CustomerAccess::isPasswordResetDenied($customer)');
if ($denyPos === false) {
    $fail('ResetPassword must call CustomerAccess::isPasswordResetDenied');
}

if ($denyPos < $validatePos) {
    $fail('isPasswordResetDenied must run after validateToken');
}

if (!str_contains($src, "ms3_customer_err_token_invalid")) {
    $fail('ResetPassword must reuse invalid-token lexicon for denied reset');
}

$tokenService = file_get_contents(__DIR__ . '/../src/Services/TokenService.php');
if ($tokenService === false) {
    $fail('cannot read TokenService.php');
}

if (!str_contains($tokenService, "resolveApiToken(\$existingToken)")) {
    $fail('generateCustomerToken must re-validate session token via resolveApiToken');
}

fwrite(STDOUT, "OK ResetPasswordAccessTest\n");
exit(0);
