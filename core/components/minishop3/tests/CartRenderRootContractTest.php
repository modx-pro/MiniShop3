<?php

/**
 * Cart SSR must resolve one render root for CartUI and QuantityUI (#804).
 *
 * Without &selector CartUI used a fallback list but silently skipped rendering
 * when several roots matched (two carts on a page is a normal layout), and
 * QuantityUI had no fallback list at all — +/- updates never redrew the cart.
 * Both modules now call the shared resolveCartRenderRoot(), which warns once
 * per token instead of failing silently.
 *
 * Run: php tests/CartRenderRootContractTest.php
 */

declare(strict_types=1);

$webDir = dirname(__DIR__, 4) . '/assets/components/minishop3/js/web';
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL CartRenderRootContractTest: {$message}\n");
    exit(1);
};

$selectorsPath = $webDir . '/core/Selectors.js';
$cartUiPath = $webDir . '/ui/CartUI.js';
$quantityUiPath = $webDir . '/ui/QuantityUI.js';
foreach ([$selectorsPath, $cartUiPath, $quantityUiPath] as $path) {
    if (!is_readable($path)) {
        $fail("Cannot read {$path}");
    }
}

$selectors = (string)file_get_contents($selectorsPath);
$cartUi = (string)file_get_contents($cartUiPath);
$quantityUi = (string)file_get_contents($quantityUiPath);

// One shared fallback list, and it must cover every documented root.
$fallbackRoots = ['#ms3oc-cart-live', '#msb-test-cart', '#msCart', '[data-ms-cart]', '.msCart'];
foreach ($fallbackRoots as $root) {
    if (!str_contains($selectors, "'{$root}'")) {
        $fail("Selectors.js is missing fallback root {$root} (#804)");
    }
}
if (!str_contains($selectors, 'function resolveCartRenderRoot (')) {
    $fail('Selectors.js must define resolveCartRenderRoot() (#804)');
}

// Ambiguous/missing roots must be diagnosable, but reported only once per token.
if (!str_contains($selectors, 'console.warn(')) {
    $fail('resolveCartRenderRoot() must warn instead of skipping silently (#804)');
}
if (!str_contains($selectors, '&selector')) {
    $fail('the warning must tell the user to add &selector (#804)');
}
if (!str_contains($selectors, 'warnedCartRenderTokens')) {
    $fail('the warning must be deduplicated per token to avoid console spam (#804)');
}
if (!str_contains($selectors, 'matched nothing')) {
    $fail('a missing configured selector must be reported, not replaced by a fallback root (#804)');
}
if (!str_contains($selectors, 'identities')) {
    $fail('the dedup key must include which roots matched (#804)');
}

// Both callers must use the shared resolver in their renderCart() path.
foreach (['CartUI' => $cartUi, 'QuantityUI' => $quantityUi] as $module => $source) {
    if (!str_contains($source, 'resolveCartRenderRoot(')) {
        $fail("{$module} must use the shared cart render resolver (#804)");
    }
    if (!str_contains($source, 'claimedRoots')) {
        $fail("{$module} must claim roots so two tokens cannot share one (#804)");
    }
    if (str_contains($source, 'fallbackSelectors')) {
        $fail("{$module} must not keep a private fallback selector list (#804)");
    }
}

// The fallback list must exist in exactly one storefront file.
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($webDir, FilesystemIterator::SKIP_DOTS)
);
$fallbackLists = 0;
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'js' || str_contains($file->getPathname(), '/lib/')) {
        continue;
    }
    if (str_contains((string)file_get_contents($file->getPathname()), "'#ms3oc-cart-live'")) {
        $fallbackLists++;
    }
}
if ($fallbackLists !== 1) {
    $fail("the fallback root list must live in one file, found {$fallbackLists} (#804)");
}

// QuantityUI used to ignore tokens without a configured selector entirely.
if (str_contains($quantityUi, 'if (!config || !config.selector)')) {
    $fail('QuantityUI must not require a configured selector to redraw the cart (#804)');
}

fwrite(STDOUT, "OK CartRenderRootContractTest (#804)\n");
exit(0);
