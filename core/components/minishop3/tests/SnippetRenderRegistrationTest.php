<?php

/**
 * #831: only HTML-rendering snippet calls may register for cart re-render.
 * A return=data call returns an array and must not be pushed into innerHTML.
 *
 * Standalone (no MODX). Run: php tests/SnippetRenderRegistrationTest.php
 */

declare(strict_types=1);

$component = dirname(__DIR__);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$cases = [
    'ms3_cart.php' => 'msCart',
    'ms3_order_total.php' => 'msOrderTotal',
];

foreach ($cases as $file => $snippetName) {
    $path = $component . '/elements/snippets/' . $file;
    $source = (string) file_get_contents($path);

    $returnPos = strpos($source, "getOption('return'");
    $registerPos = strpos($source, 'registerSnippet(');
    if ($returnPos === false || $registerPos === false || $returnPos >= $registerPos) {
        $fail("{$file}: &return must be resolved before registerSnippet()");
    }

    if (!str_contains($source, "getOption('return', \$scriptProperties, 'tpl', true)")) {
        $fail("{$file}: return must resolve with skipEmpty so a cleared property renders a chunk (#823/#831)");
    }
    if (!str_contains($source, "if (\$return === 'tpl')")) {
        $fail("{$file}: only tpl-rendering calls may register for re-render (#831)");
    }
    // The registration call must sit inside the guard, not before it.
    $guardPos = strpos($source, "if (\$return === 'tpl')");
    if ($guardPos === false || $registerPos < $guardPos) {
        $fail("{$file}: registerSnippet() is outside the tpl guard (#831)");
    }
}

$controller = (string) file_get_contents(
    $component . '/src/Controllers/Api/Web/CartController.php'
);
if (!str_contains($controller, "if (!is_string(\$html))")) {
    $fail('CartController must skip non-string render results');
}

fwrite(STDOUT, "OK SnippetRenderRegistrationTest\n");
exit(0);
