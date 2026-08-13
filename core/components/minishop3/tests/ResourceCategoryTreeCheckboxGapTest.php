<?php

/**
 * Category tree: space between PrimeVue checkbox and node label (#555).
 *
 * Run: php tests/ResourceCategoryTreeCheckboxGapTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$vue = dirname(__DIR__, 4) . '/vueManager/src/components/ResourceCategoryTree.vue';
$src = is_file($vue) ? file_get_contents($vue) : false;
if ($src === false) {
    $fail('cannot read vueManager/src/components/ResourceCategoryTree.vue');
}

if (!str_contains($src, 'class="tree-node-check"') || !str_contains($src, 'class="tree-node-label"')) {
    $fail('tree node must keep Checkbox.tree-node-check and label.tree-node-label');
}

if (preg_match('/\.tree-node-check\s*\+\s*\.tree-node-label\s*\{[^}]*margin-inline-start:\s*0\.5rem/', $src) !== 1) {
    $fail('checkbox + label must set margin-inline-start: 0.5rem (#555)');
}

if (preg_match('/\.tree-node-check\s*\{[^}]*flex-shrink:\s*0/', $src) !== 1) {
    $fail('tree-node-check must set flex-shrink: 0 so the box keeps its width');
}

$update = dirname(__DIR__) . '/controllers/product/update.class.php';
$updateSrc = is_file($update) ? file_get_contents($update) : false;
if ($updateSrc === false) {
    $fail('cannot read controllers/product/update.class.php');
}
if (preg_match('/addCss\([^;]*ResourceCategoryTree\.min\.css/', $updateSrc) !== 1) {
    $fail('product/update must load ResourceCategoryTree.min.css (Vite shared chunk, #555)');
}

fwrite(STDOUT, "OK ResourceCategoryTreeCheckboxGapTest\n");
exit(0);
