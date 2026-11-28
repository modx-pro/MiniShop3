<?php

/**
 * #873: the default source must be a real media source (id 1), the resolver
 * must not overwrite an unrelated source that happens to have that id, and
 * a stored 0 must never be re-selected.
 *
 * Run: php tests/ProductSourceDefaultTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$settings = require $root . '/_build/elements/settings.php';
if (($settings['ms3_product_source_default']['value'] ?? null) !== 1) {
    $fail('Installer default must be source id 1 (#873)');
}

$resolver = (string) file_get_contents($root . '/_build/resolvers/resolver_04_sources.php');
if (str_contains($resolver, "'id' => \$setting->get('value')")) {
    $fail('Source resolver must not match by configurable id (#873)');
}
if (!str_contains($resolver, "'name' => \$properties['name']")) {
    $fail('Source resolver must match the named MS3 source (#873)');
}

$imageService = (string) file_get_contents($root . '/core/components/minishop3/src/Services/Product/ProductImageService.php');
if (!str_contains($imageService, '$sourceId <= 0')) {
    $fail('A zero source id must fall back to the configured/real source (#873)');
}

$create = (string) file_get_contents($root . '/core/components/minishop3/controllers/product/create.class.php');
if (!str_contains($create, "getOption('ms3_product_source_default', null, 1)")) {
    $fail('Legacy create controller must fall back to source 1 (#873)');
}

fwrite(STDOUT, "OK ProductSourceDefaultTest (#873)\n");
exit(0);
