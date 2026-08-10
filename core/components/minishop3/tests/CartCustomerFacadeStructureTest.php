<?php

/**
 * Structural checks for Cart/Customer thin facades (issue #362).
 *
 * Run: php tests/CartCustomerFacadeStructureTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Cart\Cart;
use MiniShop3\Controllers\Customer\Customer;
use MiniShop3\ServiceRegistry;
use MiniShop3\Services\Cart\CartMutationHandler;
use MiniShop3\Services\Customer\CustomerFieldManager;
use MiniShop3\Services\Customer\CustomerOrderResolver;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $condition, string $label) use ($fail): void {
    if (!$condition) {
        $fail($label);
    }
};

$methodLineCount = static function (\ReflectionMethod $method): int {
    $start = $method->getStartLine();
    $end = $method->getEndLine();
    if ($start === false || $end === false) {
        return PHP_INT_MAX;
    }

    return $end - $start + 1;
};

$maxFacadeLines = 55;

$cartMethods = ['add', 'change', 'changeOption', 'remove'];
$cartReflection = new \ReflectionClass(Cart::class);
foreach ($cartMethods as $name) {
    $lines = $methodLineCount($cartReflection->getMethod($name));
    $assertTrue(
        $lines <= $maxFacadeLines,
        "Cart::{$name} is {$lines} lines (max {$maxFacadeLines})"
    );
}

$customerMethods = ['add', 'validate', 'create', 'getOrCreate'];
$customerReflection = new \ReflectionClass(Customer::class);
foreach ($customerMethods as $name) {
    $lines = $methodLineCount($customerReflection->getMethod($name));
    $assertTrue(
        $lines <= $maxFacadeLines,
        "Customer::{$name} is {$lines} lines (max {$maxFacadeLines})"
    );
}

$assertTrue(class_exists(CartMutationHandler::class), 'CartMutationHandler missing');
$assertTrue(class_exists(CustomerFieldManager::class), 'CustomerFieldManager missing');
$assertTrue(class_exists(CustomerOrderResolver::class), 'CustomerOrderResolver missing');

$registryReflection = new \ReflectionClass(ServiceRegistry::class);
$defaultsProp = $registryReflection->getProperty('defaultServices');
$defaultsProp->setAccessible(true);
// Property default value without constructing ServiceRegistry (needs modX).
$defaults = $defaultsProp->getDefaultValue();
$assertTrue(is_array($defaults), 'ServiceRegistry::$defaultServices default missing');
foreach (
    [
        'ms3_cart_mutation_handler',
        'ms3_customer_field_manager',
        'ms3_customer_order_resolver',
    ] as $key
) {
    $assertTrue(isset($defaults[$key]), "ServiceRegistry defaultServices missing {$key}");
}

// Logic for mutations must live in Services, not only in Controllers facades.
$cartSource = file_get_contents($cartReflection->getFileName() ?: '') ?: '';
$assertTrue(
    !str_contains($cartSource, 'msOnBeforeAddToCart'),
    'Cart facade still contains msOnBeforeAddToCart (should be in CartMutationHandler)'
);

$handlerSource = file_get_contents(
    (new \ReflectionClass(CartMutationHandler::class))->getFileName() ?: ''
) ?: '';
$assertTrue(
    str_contains($handlerSource, 'msOnBeforeAddToCart'),
    'CartMutationHandler must own msOnBeforeAddToCart'
);
$assertTrue(
    str_contains($handlerSource, 'buildStatus') || str_contains($handlerSource, 'msOnGetStatusCart'),
    'CartMutationHandler must own cart status for mutation responses'
);
$assertTrue(
    !str_contains($cartSource, 'statusSpreadMessages'),
    'Cart facade must not whitelist status via statusSpreadMessages'
);

fwrite(STDOUT, "OK: Cart/Customer facade structure checks passed\n");
exit(0);
