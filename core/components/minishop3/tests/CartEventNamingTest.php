<?php

/**
 * Cart event pairs must follow the naming rule "after" = "before" without `Before`
 * (#807). `msOnChangeOptionInCart` broke it, so a plugin author guessing the
 * second name of the pair got a handler that never fires.
 *
 * Run: php tests/CartEventNamingTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$eventsPath = dirname(__DIR__, 4) . '/_build/elements/events.php';
if (!is_readable($eventsPath)) {
    $fail("Missing {$eventsPath}");
}

$events = require $eventsPath;
if (!is_array($events)) {
    $fail('events.php must return an array of event names');
}

$known = array_fill_keys($events, true);

$cartBeforeEvents = [
    'msOnBeforeGetCart',
    'msOnBeforeGetCartCost',
    'msOnBeforeAddToCart',
    'msOnBeforeChangeInCart',
    'msOnBeforeChangeOptionsInCart',
    'msOnBeforeRemoveFromCart',
    'msOnBeforeEmptyCart',
];

foreach ($cartBeforeEvents as $beforeEvent) {
    if (!isset($known[$beforeEvent])) {
        $fail("events.php must register {$beforeEvent}");
    }

    $expected = 'msOn' . substr($beforeEvent, strlen('msOnBefore'));
    if (!isset($known[$expected])) {
        $fail("Cart event {$beforeEvent} has no '{$expected}' pair registered in events.php");
    }
}

// BC: the miniShop2 spelling stays registered while CartMutationHandler fires it.
if (!isset($known['msOnChangeOptionInCart'])) {
    $fail('events.php must keep the deprecated msOnChangeOptionInCart alias for BC (#807)');
}

$handlerPath = __DIR__ . '/../src/Services/Cart/CartMutationHandler.php';
$handler = file_get_contents($handlerPath);
if ($handler === false) {
    $fail("Cannot read {$handlerPath}");
}

foreach (['msOnBeforeChangeOptionsInCart', 'msOnChangeOptionsInCart', 'msOnChangeOptionInCart'] as $fired) {
    if (!str_contains($handler, "'{$fired}'")) {
        $fail("CartMutationHandler must fire {$fired}");
    }
}

if (!str_contains($handler, 'invokeChangeOptionsEvents')) {
    $fail('CartMutationHandler must fire the options pair through invokeChangeOptionsEvents()');
}

$catalogPath = __DIR__ . '/../src/Services/Events/DomainEventCatalog.php';
$catalog = file_get_contents($catalogPath);
if ($catalog === false) {
    $fail("Cannot read {$catalogPath}");
}

if (!str_contains($catalog, "CART_ITEM_OPTIONS_CHANGED => ['msOnChangeOptionsInCart']")) {
    $fail('DomainEventCatalog must bind cart.item.options_changed to msOnChangeOptionsInCart (#807)');
}

fwrite(STDOUT, 'OK CartEventNamingTest (' . count($cartBeforeEvents) . " cart pairs + BC alias #807)\n");
exit(0);