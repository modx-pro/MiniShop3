<?php

/**
 * #855: category class_key handling must use FQCN while accepting legacy short names.
 *
 * Standalone (no MODX). Run: php tests/CategoryClassKeyContractTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$model = (string) file_get_contents($root . '/src/Model/msCategory.php');
if (!str_contains($model, 'getStoredClassKey()')) {
    $fail('msCategory::save must read the original class_key from the stored row');
}
if (!str_contains($model, 'CategoryService::shouldResetOnSave(')) {
    $fail('msCategory::save must delegate the decision to CategoryService');
}
if (str_contains($model, 'parent::get(\'class_key\')')) {
    $fail('parent::get() and $this->get() read the same field — stored value required');
}

$service = (string) file_get_contents($root . '/src/Services/Category/CategoryService.php');
foreach ([
    "'class_key' => 'msCategory'",
    "'class_key' => 'msProduct'",
    "\$oldClassKey !== 'msCategory'",
] as $legacy) {
    if (str_contains($service, $legacy)) {
        $fail("CategoryService still uses a short class_key comparison: {$legacy}");
    }
}
if (substr_count($service, "'class_key:IN' => [ms") < 2) {
    $fail('both queries must accept FQCN and legacy short class keys');
}

fwrite(STDOUT, "OK CategoryClassKeyContractTest\n");
exit(0);
