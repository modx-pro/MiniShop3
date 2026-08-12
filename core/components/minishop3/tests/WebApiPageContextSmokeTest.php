<?php

/**
 * Smoke: Web API page context wiring for cart toast lexicon (#541).
 *
 * Run: php tests/WebApiPageContextSmokeTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$repoRoot = dirname(__DIR__, 4);
$core = dirname(__DIR__);

$apiPhp = file_get_contents($repoRoot . '/assets/components/minishop3/api.php');
if ($apiPhp === false || !str_contains($apiPhp, 'WebApiContextResolver::apply')) {
    $fail('api.php must apply WebApiContextResolver');
}
if (!str_contains($apiPhp, 'ms3->initialize($ctx)')) {
    $fail('api.php must initialize ms3 with resolved ctx');
}

$resolver = $core . '/src/Services/Api/WebApiContextResolver.php';
if (!is_file($resolver)) {
    $fail('WebApiContextResolver.php missing');
}

$plugin = file_get_contents($core . '/elements/plugins/minishop3.php');
if ($plugin === false
    || !str_contains($plugin, 'registerFrontend($ctx)')
    || !str_contains($plugin, '$modx->context->key')
) {
    $fail('plugin must pass page context to registerFrontend');
}

$apiClient = file_get_contents($repoRoot . '/assets/components/minishop3/js/web/core/ApiClient.js');
if ($apiClient === false
    || !str_contains($apiClient, "searchParams.set('ctx'")
    || !str_contains($apiClient, 'buildUrl')
) {
    $fail('ApiClient must send ctx via buildUrl');
}

$tokenManager = file_get_contents($repoRoot . '/assets/components/minishop3/js/web/core/TokenManager.js');
if ($tokenManager === false || !str_contains($tokenManager, 'buildUrl(')) {
    $fail('TokenManager must use ApiClient.buildUrl so token/get carries ctx');
}

fwrite(STDOUT, "OK: WebApiPageContextSmokeTest\n");
