<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

/**
 * Resolves import CSV and gallery asset paths under MODX base (path traversal guard).
 */
final class ImportCsvPathGuard
{
    public static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (str_starts_with($path, '/')) {
            return true;
        }

        return PHP_OS_FAMILY === 'Windows' && (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    /**
     * Resolve a CSV file path (relative to base or already absolute) under base directory.
     */
    public static function resolveCsvFileUnderBase(string $file, string $basePath): ?string
    {
        if ($file === '' || !preg_match('/\.csv$/i', $file)) {
            return null;
        }

        $realBasePath = realpath(rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR);
        if ($realBasePath === false) {
            return null;
        }

        $candidate = self::isAbsolutePath($file)
            ? $file
            : str_replace('//', '/', rtrim($basePath, '/\\') . '/' . ltrim($file, '/\\'));

        $realPath = realpath($candidate);
        if ($realPath === false || !str_starts_with($realPath, $realBasePath)) {
            return null;
        }

        return $realPath;
    }

    /**
     * Resolve a relative asset path (gallery image) under base directory.
     */
    public static function resolveAssetUnderBase(string $relativePath, string $basePath): ?string
    {
        if ($relativePath === '' || self::isAbsolutePath($relativePath)) {
            return null;
        }

        if (str_contains($relativePath, '..')) {
            return null;
        }

        $realBasePath = realpath(rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR);
        if ($realBasePath === false) {
            return null;
        }

        $candidate = str_replace('//', '/', rtrim($basePath, '/\\') . '/' . ltrim($relativePath, '/\\'));
        $realPath = realpath($candidate);
        if ($realPath === false || !str_starts_with($realPath, $realBasePath) || !is_file($realPath)) {
            return null;
        }

        return $realPath;
    }
}
