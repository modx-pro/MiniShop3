<?php

/**
 * Статические проверки URL подтверждения email (без MODX).
 *
 * Запуск: php tests/EmailVerificationUrlTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Customer\EmailVerificationService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$apiBase = 'https://example.org/assets/components/minishop3/api.php';
$token = 'tok&x=1';
$expectedToken = rawurlencode($token);
$expectedRoute = rawurlencode('/api/v1/customer/email/verify');
$qBase = "route={$expectedRoute}&token={$expectedToken}";

$withHtml = EmailVerificationService::buildDefaultVerifyRequestUrl($apiBase, $token, true);
$expectedWithHtml = $apiBase . '?' . $qBase . '&html=1';
if ($withHtml !== $expectedWithHtml) {
    $fail("with html: expected \n{$expectedWithHtml}\n got \n{$withHtml}");
}
if (!str_contains($withHtml, 'html=1')) {
    $fail('with html: missing html=1');
}

$withoutHtml = EmailVerificationService::buildDefaultVerifyRequestUrl($apiBase, $token, false);
$expectedNoHtml = $apiBase . '?' . $qBase;
if ($withoutHtml !== $expectedNoHtml) {
    $fail("without html: expected \n{$expectedNoHtml}\n got \n{$withoutHtml}");
}
if (str_contains($withoutHtml, 'html=1')) {
    $fail('without html: html=1 should be absent');
}

$apiWithQuery = 'https://example.org/api.php?debug=0';
$withAmp = EmailVerificationService::buildDefaultVerifyRequestUrl($apiWithQuery, 'abc', true);
if (!str_starts_with($withAmp, $apiWithQuery . '&')) {
    $fail('base URL with ? must use & to append query');
}

fwrite(STDOUT, "OK EmailVerificationUrlTest\n");
exit(0);
