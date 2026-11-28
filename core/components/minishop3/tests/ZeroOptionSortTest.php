<?php

/**
 * #859: scalar zero options must be normalized before sortOptionValues()
 * calls array_multisort(), which cannot sort a scalar.
 *
 * Run: php tests/ZeroOptionSortTest.php
 */

declare(strict_types=1);

$path = dirname(__DIR__) . '/elements/snippets/ms3_product_options.php';
$source = (string) file_get_contents($path);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

if (!str_contains($source, 'Sort a normalized copy')) {
    $fail('ms3_product_options must normalize scalar values before sorting (#859)');
}
if (!str_contains($source, "!is_array(\$option['value'])")) {
    $fail('scalar option values must be wrapped before array_multisort (#859)');
}
if (!str_contains($source, "'value'][0]")) {
    $fail('sorted scalar values must be mapped back without changing the data shape (#859)');
}

// The shared blank semantics must match the cart mutation paths.
$valuePresence = (string) file_get_contents(dirname(__DIR__) . '/src/Utils/ValuePresence.php');
if (!str_contains($valuePresence, '$value === false')) {
    $fail('false must keep its legacy blank/clear semantics (#859)');
}
foreach (['CartItemManager.php', 'CartMutationHandler.php'] as $file) {
    $code = (string) file_get_contents(dirname(__DIR__) . '/src/Services/Cart/' . $file);
    if (!str_contains($code, 'ValuePresence::merge')) {
        $fail($file . ' must use the shared option merge (#859)');
    }
}

fwrite(STDOUT, "OK ZeroOptionSortTest (#859)\n");
exit(0);
