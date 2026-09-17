<?php

declare(strict_types=1);

namespace MiniShop3\Services\Seo;

use MiniShop3\Services\Catalog\CatalogQuery;

/**
 * Core SEO projection for public catalog JSON (#567, #703).
 *
 * Fallbacks: title ← longtitle|pagetitle; description ← description|introtext;
 * canonical ← absolute site_url + uri (PublicSeoService may override via makeUrl);
 * og.image ← absolute site_url + image|thumb; robots from searchable when present.
 * og.type is product|website. Unknown keys are dropped by whitelist().
 * TV overlay and canonical makeUrl live in PublicSeoService.
 *
 * SOFT (#703): og.image uses a single URL (image|thumb). Multi-size gallery og
 * variants deferred — see issue #703 item 5 / #566.
 */
final class PublicSeoBuilder
{
    public const OG_TYPE_PRODUCT = 'product';
    public const OG_TYPE_CATEGORY = 'website';

    /** @var list<string> */
    public const KEYS = ['title', 'description', 'canonical', 'robots', 'og'];

    /** @var list<string> */
    public const OG_KEYS = ['title', 'description', 'image', 'type'];

    /**
     * Absolute URL without double slashes. Already-absolute http(s) and protocol-relative
     * paths are returned unchanged. Empty path yields empty string (not site root).
     */
    public static function absoluteUrl(string $base, string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $path) === 1) {
            return $path;
        }

        $base = rtrim(trim($base), '/');
        if ($base === '') {
            return '/' . ltrim($path, '/');
        }

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * @param array<string, mixed> $fields Public catalog payload (pagetitle, uri, image, …)
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     robots: string,
     *     og: array{title: string, description: string, image: string, type: string}
     * }
     */
    public static function build(array $fields, string $siteUrl, string $ogType): array
    {
        $title = self::field($fields, 'longtitle', 'pagetitle');
        $description = self::field($fields, 'description', 'introtext');
        $imagePath = self::field($fields, 'image', 'thumb');

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => self::absoluteUrl($siteUrl, (string) ($fields['uri'] ?? '')),
            'robots' => self::resolveRobots($fields),
            'og' => [
                'title' => $title,
                'description' => $description,
                'image' => self::absoluteUrl($siteUrl, $imagePath),
                'type' => $ogType,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $seo
     * @return array<string, mixed>
     */
    public static function whitelist(array $seo): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $seo)) {
                continue;
            }
            if ($key === 'og') {
                if (is_array($seo['og'])) {
                    $out['og'] = self::stringFields($seo['og'], self::OG_KEYS);
                }
                continue;
            }
            if (is_scalar($seo[$key]) || $seo[$key] === null) {
                $out[$key] = (string) $seo[$key];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function resolveRobots(array $fields): string
    {
        if (!array_key_exists('searchable', $fields)) {
            return 'index,follow';
        }

        return CatalogQuery::toBool($fields['searchable']) ? 'index,follow' : 'noindex,nofollow';
    }

    /**
     * First non-empty trimmed string among payload keys.
     *
     * @param array<string, mixed> $fields
     */
    private static function field(array $fields, string ...$keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $keys
     * @return array<string, string>
     */
    private static function stringFields(array $source, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $value = $source[$key];
            if (is_scalar($value) || $value === null) {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }
}
