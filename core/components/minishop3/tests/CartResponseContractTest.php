<?php

/**
 * Smoke: Web cart response projection (#570).
 *
 * Run: php tests/CartResponseContractTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$controller = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/CartController.php');
$normalizer = file_get_contents(__DIR__ . '/../src/Services/Cart/CartResponseNormalizer.php');
$registry = file_get_contents(__DIR__ . '/../src/ServiceRegistry.php');
$factories = file_get_contents(__DIR__ . '/../src/ServiceRegistryFactories.php');
$cartApi = file_get_contents(__DIR__ . '/../../../../assets/components/minishop3/js/web/core/CartAPI.js');
$apiClient = file_get_contents(__DIR__ . '/../../../../assets/components/minishop3/js/web/core/ApiClient.js');

if (
    $controller === false
    || $normalizer === false
    || $registry === false
    || $factories === false
    || $cartApi === false
    || $apiClient === false
) {
    $fail('unable to read source files');
}

foreach (['function add(', 'function change(', 'function changeOption(', 'function remove(', 'function get(', 'function clean('] as $method) {
    if (!str_contains($controller, $method)) {
        $fail("CartController missing {$method}");
    }
}

if (substr_count($controller, 'return $this->transformResponse($result);') < 6) {
    $fail('every cart mutation and get must use transformResponse');
}

if (!str_contains($controller, 'ms3_cart_response_normalizer')) {
    $fail('CartController must resolve ms3_cart_response_normalizer');
}

if (!str_contains($controller, 'include_thumbs')) {
    $fail('CartController must document include_thumbs');
}

if (!str_contains($controller, 'order/cost')) {
    $fail('CartController must document order/cost boundary');
}

if (str_contains($controller, 'OrderCostCalculator')) {
    $fail('CartController must not call OrderCostCalculator');
}

if (!str_contains($normalizer, 'new \\stdClass()')) {
    $fail('empty cart must encode as JSON object');
}

if (!str_contains($normalizer, "'items'")) {
    $fail('normalizer must project items array');
}

if (!str_contains($controller, 'CatalogQuery::toBool')) {
    $fail('CartController must parse include_thumbs at the HTTP boundary');
}

if (!str_contains($normalizer, 'bool $includeThumbs')) {
    $fail('normalizer must take includeThumbs as bool, not a request bag');
}

if (str_contains($normalizer, 'OrderCostCalculator')) {
    $fail('CartResponseNormalizer must not call OrderCostCalculator');
}

$costCalculator = file_get_contents(__DIR__ . '/../src/Services/Order/OrderCostCalculator.php');
if ($costCalculator === false) {
    $fail('unable to read OrderCostCalculator');
}

if (!str_contains($costCalculator, 'CartResponseNormalizer::projectStatus')) {
    $fail('OrderCostCalculator must round merged cart status via CartResponseNormalizer::projectStatus');
}

if (!str_contains($registry, 'ms3_cart_response_normalizer')) {
    $fail('ServiceRegistry missing ms3_cart_response_normalizer');
}

if (!str_contains($factories, "'ms3_cart_response_normalizer'")) {
    $fail('ServiceRegistryFactories missing ms3_cart_response_normalizer');
}

if (!str_contains($cartApi, 'items: []')) {
    $fail('CartAPI must document items array');
}

if (!str_contains($cartApi, 'include_thumbs=1')) {
    $fail('CartAPI.get must send include_thumbs');
}

if (!str_contains($apiClient, 'new URLSearchParams(query)')) {
    $fail('ApiClient.buildUrl must copy endpoint query next to route, not inside route');
}

fwrite(STDOUT, "OK: Cart response contract checks passed\n");
exit(0);
