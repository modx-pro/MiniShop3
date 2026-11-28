<?php

/**
 * #878: generated links must use an explicit absolute scheme so they work
 * regardless of `link_tag_scheme` and in subfolder installations.
 *
 * Run: php tests/TemplateUrlSchemeTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$dir = $root . '/core/components/minishop3/elements';
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$checked = 0;
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'tpl') {
        continue;
    }
    $source = (string) file_get_contents($file->getPathname());
    if (preg_match('/\| url\}/', $source)) {
        $fail($file->getFilename() . ': | url must specify an explicit scheme (#878)');
    }
    if (preg_match('/href="\/\{\$/', $source)) {
        $fail($file->getFilename() . ': manual leading slash before generated URL (#878)');
    }
    $checked++;
}

if ($checked === 0) {
    $fail('No templates checked');
}

fwrite(STDOUT, "OK TemplateUrlSchemeTest (#878, {$checked} templates)\n");
exit(0);
