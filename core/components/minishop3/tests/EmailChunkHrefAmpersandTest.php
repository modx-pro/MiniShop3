<?php

/**
 * Проверка email-чанков: в href не должно быть неэкранированных & (DOMDocument/htmlParseEntityRef).
 *
 * Запуск: php tests/EmailChunkHrefAmpersandTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$chunksDir = __DIR__ . '/../elements/chunks';
$files = glob($chunksDir . '/ms3_email*.tpl') ?: [];

if ($files === []) {
    $fail('No ms3_email*.tpl chunks found');
}

$hrefPattern = '/href\s*=\s*("|\')([^"\']*)\1/i';
$bareAmpersand = '/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/';

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        $fail('Cannot read ' . basename($file));
    }

    if (!preg_match_all($hrefPattern, $content, $matches, PREG_SET_ORDER)) {
        continue;
    }

    foreach ($matches as $match) {
        $href = $match[2];
        if (preg_match($bareAmpersand, $href)) {
            $fail(basename($file) . ': unescaped & in href: ' . $href);
        }
    }
}

fwrite(STDOUT, "OK EmailChunkHrefAmpersandTest (" . count($files) . " chunks)\n");
exit(0);
