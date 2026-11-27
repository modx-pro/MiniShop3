<?php

/**
 * #898: getOption() fallbacks in src must match the values the installer
 * writes into MODX. A missing setting must behave like a fresh install.
 *
 * Run: php tests/CustomerSettingDefaultsTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$root = dirname(__DIR__, 4);
$settingsPath = $root . '/_build/elements/settings.php';
$settings = is_readable($settingsPath) ? require $settingsPath : null;
if (!is_array($settings)) {
    $fail("Cannot read {$settingsPath}");
}

$sourceRoot = $root . '/core/components/minishop3/src';
if (!is_dir($sourceRoot)) {
    $fail("Missing {$sourceRoot}");
}

$sources = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $sources[] = $file->getPathname();
    }
}

$keys = [
    'ms3_customer_max_login_attempts',
    'ms3_customer_block_duration',
    'ms3_customer_require_email_verification',
];

foreach ($keys as $key) {
    if (!isset($settings[$key]) || !array_key_exists('value', $settings[$key])) {
        $fail("Installer settings.php has no value for {$key} (#898)");
    }

    $installed = $settings[$key]['value'];
    $expected = is_bool($installed) ? ($installed ? 'true' : 'false') : var_export($installed, true);

    // Third getOption() argument only; tolerate an explicit $options argument.
    $pattern = '/getOption\(\s*[\'"]' . preg_quote($key, '/') . '[\'"]\s*,\s*[^,]*,\s*([^,)]*)\s*[,)]/';
    $found = 0;
    foreach ($sources as $path) {
        $source = file_get_contents($path);
        if ($source === false) {
            $fail("Cannot read {$path}");
        }
        if (preg_match_all($pattern, $source, $matches) === 0) {
            continue;
        }
        foreach ($matches[1] as $fallback) {
            $found++;
            if (trim($fallback) !== $expected) {
                $fail("{$path}: getOption({$key}) fallback '{$fallback}' != installer value '{$expected}' (#898)");
            }
        }
    }

    if ($found === 0) {
        $fail("No getOption() fallback found for {$key} (#898)");
    }
}

fwrite(STDOUT, 'OK CustomerSettingDefaultsTest (' . count($keys) . " keys)\n");
exit(0);
