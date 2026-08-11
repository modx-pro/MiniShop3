<?php

/**
 * Orphan Ext mgr assets must stay removed (#522 / #521 Phase 0).
 *
 * Run: php tests/OrphanExtAssetsTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$componentRoot = dirname(__DIR__);
$repoRoot = dirname($componentRoot, 3);
$assetsRoot = $repoRoot . '/assets/components/minishop3/js/mgr';

$orphanFiles = [
    $assetsRoot . '/product/category.tree.js',
    $assetsRoot . '/utilities/import/panel.js',
];

foreach ($orphanFiles as $path) {
    if (is_file($path)) {
        $fail('orphan Ext asset must be deleted: ' . str_replace($repoRoot . '/', '', $path));
    }
}

$utilitiesController = file_get_contents($componentRoot . '/controllers/mgr/utilities.class.php');
if ($utilitiesController === false) {
    $fail('cannot read controllers/mgr/utilities.class.php');
}

if (preg_match('/utilities\/import\/panel\.js/', $utilitiesController) === 1) {
    $fail('utilities controller must not load utilities/import/panel.js (#522)');
}

// Import lives inside utilities.min.js after Phase 1c (#524 / #537), not a separate entry.
if (preg_match('/addVueModule\([^;]*utilities\.min\.js/', $utilitiesController) !== 1) {
    $fail('utilities controller must register utilities.min.js Vue entry (#524)');
}

if (preg_match('/addVueModule\([^;]*import\.min\.js/', $utilitiesController) === 1) {
    $fail('utilities controller must not register separate import.min.js after utilities consolidation (#524)');
}

$scanRoots = [
    $assetsRoot,
    $componentRoot . '/controllers',
];

$forbiddenPatterns = [
    'category\.tree\.js' => 'reference to deleted category.tree.js',
    'utilities/import/panel\.js' => 'reference to deleted utilities/import/panel.js',
    "Ext\.reg\('ms3-utilities-import'" => 'Ext import panel xtype must not be registered',
    "Ext\.reg\('ms3-tree-categories'" => 'Ext category tree xtype must not be registered',
];

foreach ($scanRoots as $root) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $ext = strtolower($fileInfo->getExtension());
        if (!in_array($ext, ['js', 'php'], true)) {
            continue;
        }

        $relative = str_replace($repoRoot . '/', '', $fileInfo->getPathname());
        $contents = file_get_contents($fileInfo->getPathname());
        if ($contents === false) {
            $fail('cannot read ' . $relative);
        }

        foreach ($forbiddenPatterns as $pattern => $label) {
            if (preg_match('#' . $pattern . '#', $contents) === 1) {
                $fail("{$label} in {$relative}");
            }
        }
    }
}

fwrite(STDOUT, "OK OrphanExtAssetsTest\n");
exit(0);
