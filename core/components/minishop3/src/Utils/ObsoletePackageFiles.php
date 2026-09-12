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
        if ($parts === []) {
            return null;
        }

        $rootReal = realpath($root);
        if ($rootReal === false) {
            return null;
        }

        return $rootReal . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * @param list<string> $relativePaths
     * @return array{removed: list<string>, skipped: list<string>, rejected: list<string>}
     */
    public static function purge(string $root, array $relativePaths): array
    {
        $removed = [];
        $skipped = [];
        $rejected = [];

        $rootReal = realpath($root);
        if ($rootReal === false) {
            return ['removed' => [], 'skipped' => [], 'rejected' => $relativePaths];
        }
        $prefix = $rootReal . DIRECTORY_SEPARATOR;

        foreach ($relativePaths as $relative) {
            $candidate = self::resolveUnderRoot($rootReal, $relative);
            if ($candidate === null) {
                $rejected[] = $relative;
                continue;
            }
            if (!is_file($candidate)) {
                $skipped[] = $relative;
                continue;
            }

            $realFile = realpath($candidate);
            if ($realFile === false || !str_starts_with($realFile, $prefix)) {
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
        ];
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
