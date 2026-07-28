<?php

namespace MiniShop3\Utils;

/**
 * Pure CORS config normalization (no MODX). Issue #335.
 */
final class CorsConfig
{
    /**
     * Normalize CORS middleware config. Wildcard origins cannot be combined with credentials.
     *
     * @param array<string, mixed> $config
     * @return array{
     *     allowed_origins: string[],
     *     allowed_methods: string[],
     *     allowed_headers: string[],
     *     allow_credentials: bool,
     *     max_age: int
     * }
     */
    public static function normalizeCorsConfig(array $config): array
    {
        $allowedOrigins = self::normalizeOriginsList($config['allowed_origins'] ?? '');
        $allowCredentials = (bool) ($config['allow_credentials'] ?? true);

        if (self::hasWildcardOrigin($allowedOrigins) && $allowCredentials) {
            $allowCredentials = false;
        }

        return [
            'allowed_origins' => $allowedOrigins,
            'allowed_methods' => self::normalizeStringList(
                $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
            ),
            'allowed_headers' => self::normalizeStringList(
                $config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With', 'MS3TOKEN']
            ),
            'allow_credentials' => $allowCredentials,
            'max_age' => max(0, (int) ($config['max_age'] ?? 86400)),
        ];
    }

    /**
     * @return string[]
     */
    public static function normalizeOriginsList(mixed $value): array
    {
        return self::normalizeStringList($value);
    }

    public static function hasWildcardOrigin(array $origins): bool
    {
        return in_array('*', $origins, true);
    }

    /**
     * @return string[]
     */
    public static function normalizeStringList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } elseif (str_contains($trimmed, ',')) {
                $items = explode(',', $trimmed);
            } else {
                $items = [$trimmed];
            }
        } else {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_string($item) && !is_numeric($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return $result;
    }
}
