<?php

/**
 * Smoke #652: product update must not push undefined access/settings tabs into Ext items.
 *
 * Run: php tests/ProductUpdateAccessTabGuardTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL ProductUpdateAccessTabGuardTest: {$message}\n");
    exit(1);
};

$repoRoot = dirname(__DIR__, 4);
$productUpdate = $repoRoot . '/assets/components/minishop3/js/mgr/product/update.js';
$categoryUpdate = $repoRoot . '/assets/components/minishop3/js/mgr/category/update.js';

foreach ([$productUpdate, $categoryUpdate] as $path) {
    if (!is_readable($path)) {
        $fail('not readable: ' . $path);
    }
}

$productSrc = file_get_contents($productUpdate);
if ($productSrc === false) {
    $fail('cannot read product/update.js');
}

// Unguarded pushes regress #652 (undefined tab → blank form for managers without resourcegroup_resource_list).
if (preg_match('/^\s*tabs\.push\(pageSettingsTab\);\s*$/m', $productSrc) === 1) {
    $fail('product/update.js must guard pageSettingsTab before tabs.push (#652)');
}
if (preg_match('/^\s*tabs\.push\(accessPermissionsTab\);\s*$/m', $productSrc) === 1) {
    $fail('product/update.js must guard accessPermissionsTab before tabs.push (#652)');
}

if (!str_contains($productSrc, 'pageSettingsTab && tabs.push(pageSettingsTab)')) {
    $fail('product/update.js missing pageSettingsTab && tabs.push guard');
}
if (!str_contains($productSrc, 'accessPermissionsTab && tabs.push(accessPermissionsTab)')) {
    $fail('product/update.js missing accessPermissionsTab && tabs.push guard');
}

$categorySrc = file_get_contents($categoryUpdate);
if ($categorySrc === false) {
    $fail('cannot read category/update.js');
}
if (!str_contains($categorySrc, 'accessPermissionsTab && tabs.push(accessPermissionsTab)')) {
    $fail('category/update.js lost accessPermissionsTab guard (parity reference)');
}

fwrite(STDOUT, "OK ProductUpdateAccessTabGuardTest\n");
exit(0);
