<?php

/**
 * #688: Product/Autocomplete processor is removed (SQL injection via name/query).
 * Vue autocomplete uses Manager REST, not the connector processor.
 *
 * Run: php tests/ProductAutocompleteRemovedTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL ProductAutocompleteRemovedTest: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $cond, string $case) use ($fail): void {
    if (!$cond) {
        $fail($case);
    }
};

$root = dirname(__DIR__);
$src = $root . '/src';
$vueCombo = dirname(__DIR__, 4) . '/vueManager/src/components/AutocompleteCombo.vue';
$processor = $src . '/Processors/Product/Autocomplete.php';

$assertTrue(!is_file($processor), 'orphan Product/Autocomplete.php must be deleted');
$assertTrue(
    !class_exists(\MiniShop3\Processors\Product\Autocomplete::class, true),
    'MiniShop3\\Processors\\Product\\Autocomplete must not autoload'
);

$combo = is_file($vueCombo) ? file_get_contents($vueCombo) : false;
$assertTrue($combo !== false, 'AutocompleteCombo.vue missing');
$assertTrue(
    str_contains((string) $combo, "/api/mgr/references/autocomplete"),
    'AutocompleteCombo must call Manager REST autocomplete'
);
$assertTrue(
    !str_contains((string) $combo, 'Product/Autocomplete')
    && !str_contains((string) $combo, 'action: \'Autocomplete\''),
    'AutocompleteCombo must not call the removed processor'
);

$allowlist = file_get_contents(__DIR__ . '/ProcessorPermissionsSmokeTest.php');
$assertTrue(
    is_string($allowlist) && !str_contains($allowlist, 'Product/Autocomplete.php'),
    'ProcessorPermissionsSmokeTest must not allowlist the removed processor'
);

$processorsRoot = $src . '/Processors';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($processorsRoot, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    if ($source === false) {
        $fail('cannot read ' . $file->getPathname());
    }
    if (preg_match('/->where\s*\(\s*[\'"].*\{\$.*\}/s', $source) === 1) {
        $relative = substr($file->getPathname(), strlen($processorsRoot) + 1);
        $fail("string-interpolated where() in Processors/{$relative}");
    }
}

fwrite(STDOUT, "OK ProductAutocompleteRemovedTest\n");
exit(0);
