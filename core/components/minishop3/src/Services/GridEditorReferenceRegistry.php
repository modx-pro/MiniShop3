<?php

namespace MiniShop3\Services;

/**
 * Whitelist of grid inline-edit combo references (key → manager API path).
 * Single place for validation, resolution, and client metadata.
 */
final class GridEditorReferenceRegistry
{
    /** @var array<string, string> */
    private const REFERENCES = [
        'vendors' => '/api/mgr/references/vendors',
    ];

    private const ALLOWED_PATH_PREFIX = '/api/mgr/references/';

    /**
     * @return list<array{key: string, path: string}>
     */
    public static function listForClient(): array
    {
        $out = [];
        foreach (self::REFERENCES as $key => $path) {
            $out[] = ['key' => $key, 'path' => $path];
        }

        return $out;
    }

    public static function pathFor(string $referenceKey): ?string
    {
        $k = trim($referenceKey);

        return self::REFERENCES[$k] ?? null;
    }

    public static function isAllowlistedEndpoint(string $url): bool
    {
        $trim = trim($url);
        if ($trim === '') {
            return false;
        }
        if ($trim[0] !== '/') {
            return false;
        }
        $path = parse_url($trim, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $trim;
        }

        return str_starts_with($path, self::ALLOWED_PATH_PREFIX);
    }

    /**
     * Validate combo editor configuration before persisting.
     *
     * @param array<string, mixed> $config Field JSON config (may include editor_*)
     * @return array{success: bool, message?: string}
     */
    public static function validateComboEditorConfig(array $config): array
    {
        if (($config['editor_type'] ?? '') !== GridColumnEditorType::COMBO) {
            return ['success' => true];
        }

        $ref = trim((string)($config['editor_reference'] ?? ''));
        $endpoint = trim((string)($config['editor_combo_endpoint'] ?? ''));

        $refPath = $ref !== '' ? self::pathFor($ref) : null;
        if ($ref !== '' && $refPath === null) {
            return ['success' => false, 'message' => 'Unknown editor_reference'];
        }
        if ($endpoint !== '' && !self::isAllowlistedEndpoint($endpoint)) {
            return [
                'success' => false,
                'message' => 'editor_combo_endpoint must start with path ' . self::ALLOWED_PATH_PREFIX,
            ];
        }
        if ($refPath === null && $endpoint === '') {
            return [
                'success' => false,
                'message' => 'Combo editor requires a known editor_reference or an allowlisted editor_combo_endpoint',
            ];
        }

        return ['success' => true];
    }
}
