<?php

/**
 * Quick Create must handle MODX 3 class keys (#870).
 *
 * The window override checked the raw class_key against the short aliases
 * ('msProduct' / 'msCategory'), but MODX 3 stores the FQCN
 * (MiniShop3\Model\msProduct / MiniShop3\Model\msCategory) in class_key, so the
 * template block never ran. The override must normalize the key first and accept
 * both the FQCN and legacy short aliases.
 *
 * Run: php tests/QuickCreateClassKeyTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL QuickCreateClassKeyTest: {$message}\n");
    exit(1);
};

$sourcePath = dirname(__DIR__, 4) . '/assets/components/minishop3/js/mgr/misc/ms3.manager.js';
if (!is_readable($sourcePath)) {
    $fail('Cannot read ' . $sourcePath);
}

$source = (string)file_get_contents($sourcePath);

if (
    !str_contains($source, 'shortClassKey = String(classKey || \'\')')
    || !str_contains($source, '.split(\'\\\\\')')
    || !str_contains($source, '.pop()')
) {
    $fail('QuickCreate override must normalize class_key to a short class name (#870)');
}
if (!str_contains($source, "isProduct = shortClassKey === 'msProduct'")) {
    $fail('isProduct must compare against the normalized short class key (#870)');
}
if (!str_contains($source, "['msProduct', 'msCategory'].includes(shortClassKey)")) {
    $fail('QuickCreate must treat the normalized short class key as a MiniShop product/category (#870)');
}
if (str_contains($source, "isProduct = classKey === 'MiniShop3")) {
    $fail('QuickCreate must not compare the raw class_key against the short alias (#870)');
}

// Exercise the normalization at runtime, not just as source text.
$nodeSnippet = <<<'JS'
const normalize = key => String(key || '').split('\\').pop();
const cases = [
  ['MiniShop3\\Model\\msProduct', 'msProduct'],
  ['MiniShop3\\Model\\msCategory', 'msCategory'],
  ['msProduct', 'msProduct'],
  ['', ''],
];
for (const [input, expected] of cases) {
  const actual = normalize(input);
  if (actual !== expected) {
    console.error(`${input} -> ${actual}, expected ${expected}`);
    process.exit(1);
  }
}
JS;
$tmp = tempnam(sys_get_temp_dir(), 'ms3-qc-');
file_put_contents($tmp, $nodeSnippet);
exec('node ' . escapeshellarg($tmp) . ' 2>&1', $output, $code);
unlink($tmp);
if ($code !== 0) {
    $fail('class_key normalization does not handle FQCN/short aliases: ' . implode(' ', $output));
}

fwrite(STDOUT, "OK QuickCreateClassKeyTest (#870)\n");
exit(0);
