<?php

/**
 * Contract check for Web API payload shape used by AuthUI (data vs legacy object).
 *
 * Mirrors ApiClient.getPayload() in assets/.../ApiClient.js.
 * Run: php tests/ApiClientGetPayloadTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$getPayload = static function ($result) {
    if (!$result) {
        return null;
    }
    return $result['data'] ?? $result['object'] ?? null;
};

$web = $getPayload([
    'success' => true,
    'data' => ['token' => 'abc', 'redirect_url' => '/profile'],
    'object' => null,
]);
if (!is_array($web) || ($web['redirect_url'] ?? '') !== '/profile') {
    $fail('Web API data payload must expose redirect_url');
}

$legacy = $getPayload([
    'success' => true,
    'object' => ['token' => 'xyz', 'redirect_url' => '/cabinet'],
]);
if (!is_array($legacy) || ($legacy['redirect_url'] ?? '') !== '/cabinet') {
    $fail('Legacy object payload must expose redirect_url');
}

$preferData = $getPayload([
    'data' => ['redirect_url' => '/from-data'],
    'object' => ['redirect_url' => '/from-object'],
]);
if (($preferData['redirect_url'] ?? '') !== '/from-data') {
    $fail('data must win over object');
}

if ($getPayload(null) !== null) {
    $fail('null input must return null');
}

fwrite(STDOUT, "OK ApiClientGetPayloadTest\n");
exit(0);
