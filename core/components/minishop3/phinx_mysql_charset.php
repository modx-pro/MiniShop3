<?php

/** MySQL charset helpers for phinx.php and unit tests (no MODX). */

declare(strict_types=1);

if (!function_exists('ms3PhinxExtractDsnCharset')) {
    function ms3PhinxExtractDsnCharset(?string $dsn): ?string
    {
        if ($dsn === null || $dsn === '') {
            return null;
        }

        if (preg_match('/(?:^|[;:])charset=([^;]+)/i', $dsn, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}

if (!function_exists('ms3PhinxNormalizeMysqlCharset')) {
    function ms3PhinxNormalizeMysqlCharset(?string $charset, bool $preferUtf8mb4 = false): string
    {
        $normalized = strtolower(trim((string) $charset));
        $normalized = str_replace('-', '', $normalized);

        return match ($normalized) {
            '', 'utf8', 'utf8mb3' => $preferUtf8mb4 ? 'utf8mb4' : 'utf8',
            'utf8mb4' => 'utf8mb4',
            default => preg_replace('/[^a-z0-9_]/', '', $normalized) ?: 'utf8mb4',
        };
    }
}

if (!function_exists('ms3PhinxDefaultMysqlCollation')) {
    function ms3PhinxDefaultMysqlCollation(string $charset): string
    {
        return match ($charset) {
            'utf8' => 'utf8_general_ci',
            'utf8mb4' => 'utf8mb4_unicode_ci',
            default => $charset . '_general_ci',
        };
    }
}
