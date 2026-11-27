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
 * Invalid JSON, dropped pairs and missing TVs are still ignored (no-op), but
 * reported with a WARN in the MODX log so the reason is discoverable (#808).
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
     * All problems are collected into one WARN per parse: a bad setting must
     * never break the API response, and one broken pair must not spam the log
     * on every catalog request (#808).
     *
     * @return array<string, string>
     */
    public static function parseSettingValue(mixed $raw, ?modX $modx = null): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            if ($modx !== null && $raw !== null && $raw !== '') {
                self::warn($modx, ['setting value is empty or not a string']);
            }

            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            if ($modx !== null) {
                // Valid JSON of another type (5, "text", true, null) has no parse
                // error — calling it "invalid JSON" would mislead the reader (#808).
                $why = json_last_error() === JSON_ERROR_NONE
                    ? 'setting must be a JSON object'
                    : 'invalid JSON: ' . json_last_error_msg();
                self::warn($modx, [$why]);
            }

            return [];
        }

        $map = [];
        $problems = [];
        foreach ($decoded as $seoKey => $tvRef) {
            // json_decode(..., true) only produces int|string keys; encode them
            // so a crafted key with a newline cannot forge extra log lines.
            $encodedKey = json_encode((string) $seoKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $label = $encodedKey === false ? '"?"' : $encodedKey;

            if (!is_string($seoKey) || !is_string($tvRef)) {
                $problems[] = "{$label}: key and value must be strings";
                continue;
            }

            $seoKey = trim($seoKey);
            $tvRef = trim($tvRef);
            if ($seoKey === '' || $tvRef === '') {
                $problems[] = "{$label}: empty key or TV reference";
                continue;
            }

            if (!self::isAllowedSeoKey($seoKey)) {
                // The allowlist is spelled out in the setting description (ru/en).
                $problems[] = "{$label}: seo key not allowlisted";
                continue;
            }

            $tvName = self::parseTvReference($tvRef);
            if ($tvName === null) {
                $problems[] = "{$label}: invalid TV reference";
                continue;
            }

            $map[$seoKey] = $tvName;
        }

        if ($problems !== [] && $modx !== null) {
            self::warn($modx, $problems);
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

            // tvCache stores null too, so this block runs once per missing name
            // per instance. List/tree calls overlay for every item, but the
            // service (and its tvMap) lives for one request (#808).
            if ($tv === null) {
                $this->modx->log(
                    modX::LOG_LEVEL_WARN,
                    '[MiniShop3] ' . self::SETTING_KEY . ': TV "' . $tvName . '" not found; SEO overlay skipped for it.'
                );
            }
        }

        return $this->tvCache[$tvName];
    }

    /**
     * One WARN per parse keeps a broken setting visible without log spam (#808).
     *
     * @param list<string> $problems
     */
    private static function warn(modX $modx, array $problems): void
    {
        $shown = array_slice($problems, 0, 5);
        $message = '[MiniShop3] ' . self::SETTING_KEY . ': ignored ' . count($problems) . ' problem(s): '
            . implode('; ', $shown);
        if (count($problems) > count($shown)) {
            $message .= ' (+' . (count($problems) - count($shown)) . ' more)';
        }

        $modx->log(modX::LOG_LEVEL_WARN, $message);
    }
}
