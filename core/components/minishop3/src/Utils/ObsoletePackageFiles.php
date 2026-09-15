<?php

declare(strict_types=1);

namespace MiniShop3\Utils;

/**
 * Delete leftover Extra files that MODX upgrade leaves on disk (#704).
 */
final class ObsoletePackageFiles
{
    /**
     * @return array{core: list<string>, assets: list<string>}
     */
    public static function load(string $configFile): array
    {
        $empty = ['core' => [], 'assets' => []];
        if (!is_file($configFile)) {
            return $empty;
        }

        $data = require $configFile;
        if (!is_array($data)) {
            return $empty;
        }

        return [
            'core' => self::stringList($data['core'] ?? []),
            'assets' => self::stringList($data['assets'] ?? []),
        ];
    }

    /**
     * Resolve a relative path under $root. Rejects absolute paths and `..`.
     */
    public static function resolveUnderRoot(string $root, string $relative): ?string
    {
        $parts = self::relativeSegments($relative);
        if ($parts === null) {
            return null;
        }

        $rootReal = realpath($root);
        if ($rootReal === false) {
            return null;
        }

        return $rootReal . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * True when an obsolete assets path is still listed in ms3_frontend_assets (#728).
     *
     * @param array{jsUrl?: string, cssUrl?: string, assetsUrl?: string} $placeholders
     */
    public static function isReferencedByFrontendAssets(
        string $relative,
        string $setting,
        array $placeholders = [],
    ): bool {
        $relative = str_replace('\\', '/', $relative);
        $haystack = str_replace('\\/', '/', $setting);
        $jsUrl = (string) ($placeholders['jsUrl'] ?? '');
        $cssUrl = (string) ($placeholders['cssUrl'] ?? '');
        $assetsUrl = (string) ($placeholders['assetsUrl'] ?? '');
        $expanded = str_replace(
            ['[[+jsUrl]]', '[[+cssUrl]]', '[[+assetsUrl]]'],
            [$jsUrl, $cssUrl, $assetsUrl],
            $haystack,
        );

        $needles = [$relative];
        if (str_starts_with($relative, 'js/')) {
            $suffix = substr($relative, 3);
            $needles[] = '[[+jsUrl]]' . $suffix;
            if ($jsUrl !== '') {
                $needles[] = $jsUrl . $suffix;
            }
        }
        if (str_starts_with($relative, 'css/')) {
            $suffix = substr($relative, 4);
            $needles[] = '[[+cssUrl]]' . $suffix;
            if ($cssUrl !== '') {
                $needles[] = $cssUrl . $suffix;
            }
        }
        if ($assetsUrl !== '') {
            $needles[] = $assetsUrl . $relative;
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && (str_contains($haystack, $needle) || str_contains($expanded, $needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $relativePaths
     * @param list<string> $keepRelative paths still referenced (do not delete)
     * @return array{removed: list<string>, skipped: list<string>, rejected: list<string>, kept: list<string>}
     */
    public static function purge(string $root, array $relativePaths, array $keepRelative = []): array
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            return ['removed' => [], 'skipped' => [], 'rejected' => $relativePaths, 'kept' => []];
        }

        $keep = array_fill_keys($keepRelative, true);
        $prefix = $rootReal . DIRECTORY_SEPARATOR;
        $removed = [];
        $skipped = [];
        $rejected = [];
        $kept = [];

        foreach ($relativePaths as $relative) {
            if (isset($keep[$relative])) {
                $kept[] = $relative;
                continue;
            }

            $candidate = self::resolveUnderRoot($rootReal, $relative);
            if ($candidate === null) {
                $rejected[] = $relative;
                continue;
            }

            $realFile = realpath($candidate);
            if ($realFile === false) {
                $skipped[] = $relative;
                continue;
            }
            if (!is_file($realFile) || !str_starts_with($realFile, $prefix)) {
                $rejected[] = $relative;
                continue;
            }
            if (@unlink($realFile)) {
                $removed[] = $relative;
            } else {
                $rejected[] = $relative;
            }
        }

        return [
            'removed' => $removed,
            'skipped' => $skipped,
            'rejected' => $rejected,
            'kept' => $kept,
        ];
    }

    /**
     * @return list<string>|null
     */
    private static function relativeSegments(string $relative): ?array
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || str_contains($relative, "\0")) {
            return null;
        }
        if (str_starts_with($relative, '/') || preg_match('#^[A-Za-z]:/#', $relative) === 1) {
            return null;
        }

        $parts = [];
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                return null;
            }
            $parts[] = $segment;
        }

        return $parts === [] ? null : $parts;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }
}
