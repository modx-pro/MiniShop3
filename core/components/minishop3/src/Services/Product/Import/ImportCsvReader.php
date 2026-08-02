<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MODX\Revolution\modX;

/**
 * CSV encoding detection, normalization, preview and row counting.
 */
final class ImportCsvReader
{
    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * Prepare file for import: detect encoding, remove BOM, convert to UTF-8.
     *
     * @return string Path to prepared file (may be same as original if already UTF-8)
     */
    public function prepareFile(string $filePath, ImportCsvContext $ctx): string
    {
        $handle = fopen($filePath, 'r');
        $sample = fread($handle, 8192);
        fclose($handle);

        $sampleClean = self::removeBom($sample);
        $encoding = self::detectEncoding($sampleClean);
        $ctx->detectedEncoding = $encoding;

        $this->modx->log(modX::LOG_LEVEL_INFO, "[Import] Detected encoding: {$encoding}");

        $hasBom = $sample !== $sampleClean;

        if (strtoupper($encoding) === 'UTF-8' && !$hasBom) {
            return $filePath;
        }

        $tempPath = sys_get_temp_dir() . '/ms3_import_' . uniqid() . '.csv';

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[Import] Converting file from {$encoding} to UTF-8" . ($hasBom ? ' (removing BOM)' : '')
        );

        $sourceHandle = fopen($filePath, 'r');
        $destHandle = fopen($tempPath, 'w');

        $isFirstChunk = true;
        while (!feof($sourceHandle)) {
            $chunk = fread($sourceHandle, 65536);

            if ($isFirstChunk) {
                $chunk = self::removeBom($chunk);
                $isFirstChunk = false;
            }

            if (strtoupper($encoding) !== 'UTF-8') {
                $chunk = self::convertToUtf8($chunk, $encoding);
            }

            fwrite($destHandle, $chunk);
        }

        fclose($sourceHandle);
        fclose($destHandle);

        register_shutdown_function(static function () use ($tempPath): void {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        });

        return $tempPath;
    }

    public static function countRows(string $filePath, string $delimiter = ';'): int
    {
        if (!file_exists($filePath)) {
            return 0;
        }

        $count = 0;
        $handle = fopen($filePath, 'r');
        while (fgetcsv($handle, 0, $delimiter) !== false) {
            $count++;
        }
        fclose($handle);

        return $count;
    }

    /**
     * @return array{rows: list<list<string>>, encoding: string|null}
     */
    public static function getPreview(
        string $filePath,
        string $delimiter = ';',
        int $rows = 5,
        bool $skipHeader = false,
    ): array {
        if (!file_exists($filePath)) {
            return ['rows' => [], 'encoding' => null];
        }

        $content = file_get_contents($filePath);
        $originalEncoding = self::detectEncoding($content);

        $content = self::removeBom($content);
        if (strtoupper($originalEncoding) !== 'UTF-8') {
            $content = self::convertToUtf8($content, $originalEncoding);
        }

        $preview = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $headerSkipped = false;

        while (($csv = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($skipHeader && !$headerSkipped) {
                $headerSkipped = true;
                continue;
            }

            $preview[] = $csv;

            if (count($preview) >= $rows) {
                break;
            }
        }
        fclose($handle);

        return [
            'rows' => $preview,
            'encoding' => $originalEncoding,
        ];
    }

    /**
     * @return list<string>
     */
    public static function detectHeaders(string $filePath, string $delimiter = ';'): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath, false, null, 0, 8192);
        $content = self::removeBom($content);
        $content = self::convertToUtf8($content);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter);
        fclose($handle);

        return $headers ?: [];
    }

    public static function detectEncoding(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }

        if (str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF")) {
            return 'UTF-16';
        }

        $encodings = ['UTF-8', 'Windows-1251', 'KOI8-R', 'ISO-8859-5', 'ASCII'];
        $detected = mb_detect_encoding($content, $encodings, true);

        if ($detected) {
            return $detected;
        }

        $hasUtf8Cyrillic = preg_match('/[\xD0-\xD1][\x80-\xBF]/u', $content);
        $hasWin1251Cyrillic = preg_match('/[\xC0-\xFF]/', $content) && !$hasUtf8Cyrillic;

        if ($hasWin1251Cyrillic) {
            return 'Windows-1251';
        }

        if ($hasUtf8Cyrillic) {
            return 'UTF-8';
        }

        return 'UTF-8';
    }

    public static function removeBom(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        if (str_starts_with($content, "\xFF\xFE")) {
            return substr($content, 2);
        }

        if (str_starts_with($content, "\xFE\xFF")) {
            return substr($content, 2);
        }

        return $content;
    }

    public static function convertToUtf8(string $content, ?string $fromEncoding = null): string
    {
        if ($fromEncoding === null) {
            $fromEncoding = self::detectEncoding($content);
        }

        if (strtoupper($fromEncoding) === 'UTF-8') {
            return $content;
        }

        if (function_exists('iconv')) {
            $converted = @iconv($fromEncoding, 'UTF-8//TRANSLIT//IGNORE', $content);
            if ($converted !== false) {
                return $converted;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($content, 'UTF-8', $fromEncoding);
        }

        return $content;
    }
}
