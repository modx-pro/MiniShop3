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
if (!str_contains($apiPhp, "\$_REQUEST['ctx']")) {
    $fail('api.php must read ctx from $_REQUEST (same as route)');
}
if (!str_contains($apiPhp, 'catch (\\Throwable')) {
    $fail('api.php must catch Throwable (TypeError from null context)');
}

$resolver = file_get_contents($core . '/src/Services/Api/WebApiContextResolver.php');
if ($resolver === false
    || !str_contains($resolver, 'getContext')
    || !str_contains($resolver, 'ensureLiveContext')
) {
    $fail('WebApiContextResolver must check getContext and restore on failed switch');
}

$draftCtx = $core . '/src/Services/Cart/CartDraftContext.php';
if (!is_file($draftCtx)) {
    $fail('CartDraftContext.php missing (shared Cart/Order draft key)');
}

$cart = file_get_contents($core . '/src/Controllers/Cart/Cart.php');
$order = file_get_contents($core . '/src/Controllers/Order/Order.php');
if ($cart === false || !str_contains($cart, 'CartDraftContext::resolve')) {
    $fail('Cart must resolve draft ctx via CartDraftContext');
}
if ($order === false || !str_contains($order, 'CartDraftContext::resolve')) {
    $fail('Order must resolve draft ctx via CartDraftContext');
}

$cartController = file_get_contents($core . '/src/Controllers/Api/Web/CartController.php');
if ($cartController === false
    || !str_contains($cartController, 'pageContextKey')
    || !str_contains($cartController, 'WebApiContextResolver::liveContextKey')
) {
    $fail('CartController must use guarded pageContextKey');
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
