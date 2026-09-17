<?php

declare(strict_types=1);

namespace MiniShop3\Services\Seo;

use MODX\Revolution\modTemplateVar;
use MODX\Revolution\modX;

/**
 * Optional TV overlay for public SEO (#703).
 *
 * System setting ms3_public_seo_tv_map: JSON object mapping allowlisted seo keys
 * to tv.* references, e.g. {"title":"tv.seo_title","robots":"tv.robots"}.
 * Invalid JSON or unknown keys are ignored (no-op).
 */
final class PublicSeoTvMap
{
    public const SETTING_KEY = 'ms3_public_seo_tv_map';

    /** Safe TV name after the tv. prefix (alnum, underscore, dot, hyphen). */
    private const TV_NAME_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_.-]{0,99}$/';

    /** @var array<string, modTemplateVar|null> */
    private array $tvCache = [];

    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * Parse setting value into a validated seoKey => tvRef map.
     *
     * @return array<string, string>
     */
    public static function parseSettingValue(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $seoKey => $tvRef) {
            if (!is_string($seoKey) || !is_string($tvRef)) {
                continue;
            }

            $seoKey = trim($seoKey);
            $tvRef = trim($tvRef);
            if ($seoKey === '' || $tvRef === '') {
                continue;
            }

            if (!self::isAllowedSeoKey($seoKey)) {
                continue;
            }

            $tvName = self::parseTvReference($tvRef);
            if ($tvName === null) {
                continue;
            }

            $map[$seoKey] = $tvName;
        }

        return $map;
    }

    /**
     * Load mapped TV values for a resource and merge into the seo array.
     *
     * Relative canonical and og.image values are resolved with {@see PublicSeoBuilder::absoluteUrl}.
     * SOFT (#703): batch prefetch of TV values across resource IDs is deferred.
     *
     * @param array<string, mixed> $seo
     * @param array<string, string> $map seoKey => TV name (without tv. prefix)
     * @return array{0: array<string, mixed>, 1: list<string>} seo and applied allowlisted keys
     */
    public function overlay(array $seo, int $resourceId, array $map, string $siteUrl = ''): array
    {
        /** @var list<string> $applied */
        $applied = [];

        if ($resourceId <= 0 || $map === []) {
            return [$seo, $applied];
        }

        foreach ($map as $seoKey => $tvName) {
            $value = $this->loadTvValue($resourceId, $tvName);
            if ($value === '') {
                continue;
            }

            if ($seoKey === 'canonical' || $seoKey === 'og.image') {
                $value = PublicSeoBuilder::absoluteUrl($siteUrl, $value);
            }

            $ogKey = self::ogSubKey($seoKey);
            if ($ogKey !== null) {
                $og = isset($seo['og']) && is_array($seo['og']) ? $seo['og'] : [];
                $og[$ogKey] = $value;
                $seo['og'] = $og;
                $applied[] = $seoKey;
                continue;
            }

            $seo[$seoKey] = $value;
            $applied[] = $seoKey;
        }

        return [$seo, $applied];
    }

    private static function isAllowedSeoKey(string $key): bool
    {
        if ($key !== 'og' && in_array($key, PublicSeoBuilder::KEYS, true)) {
            return true;
        }

        return self::ogSubKey($key) !== null;
    }

    private static function ogSubKey(string $seoKey): ?string
    {
        if (!str_starts_with($seoKey, 'og.')) {
            return null;
        }

        $ogKey = substr($seoKey, 3);

        return in_array($ogKey, PublicSeoBuilder::OG_KEYS, true) ? $ogKey : null;
    }

    /**
     * Accepts "tv.name" or "name"; returns normalized TV name or null when unsafe.
     */
    private static function parseTvReference(string $ref): ?string
    {
        $name = str_starts_with($ref, 'tv.') ? substr($ref, 3) : $ref;
        $name = trim($name);
        if ($name === '' || preg_match(self::TV_NAME_PATTERN, $name) !== 1) {
            return null;
        }

        return $name;
    }

    private function loadTvValue(int $resourceId, string $tvName): string
    {
        $tv = $this->resolveTv($tvName);
        if ($tv === null) {
            return '';
        }

        return trim((string) $tv->renderOutput($resourceId));
    }

    private function resolveTv(string $tvName): ?modTemplateVar
    {
        if (!array_key_exists($tvName, $this->tvCache)) {
            /** @var modTemplateVar|null $tv */
            $tv = $this->modx->getObject(modTemplateVar::class, ['name' => $tvName]);
            $this->tvCache[$tvName] = $tv;
        }

        return $this->tvCache[$tvName];
    }
}
