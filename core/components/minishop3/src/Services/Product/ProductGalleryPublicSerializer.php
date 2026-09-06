<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

/**
 * Public storefront gallery DTO (#566).
 *
 * There is no DB `alt` column: alt is derived from name, then product pagetitle.
 * include_images=1 with no files yields `images: []` (key present, empty list).
 * `thumb` prefers ms3_product_thumbnail_size (mgr GetList path match), else first child, else url.
 */
final class ProductGalleryPublicSerializer
{
    public const MAX_IMAGES = 50;
    public const MAX_IMAGES_LIST = 10;

    /** @var list<string> */
    public const ITEM_KEYS = [
        'id',
        'url',
        'thumb',
        'thumbs',
        'name',
        'description',
        'alt',
        'position',
        'is_preview',
    ];

    /**
     * Size folder from child path (`{productId}/{size}/`) or url (`/{id}/{size}/`).
     */
    public static function sizeKeyFromChild(string $path, string $url, int $productId): string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');
        if ($normalized !== '') {
            $parts = explode('/', $normalized);
            $last = (string) end($parts);
            if ($last !== '' && ($productId <= 0 || $last !== (string) $productId)) {
                return $last;
            }
        }

        if ($productId > 0 && preg_match('#/' . preg_quote((string) $productId, '#') . '/([^/]+)/#', $url, $m) === 1) {
            return $m[1];
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $originals Top-level files (already filtered/sorted/capped)
     * @param array<int, array{thumb?: string, thumbs?: array<string, string>}> $thumbsByParent
     * @return list<array<string, mixed>>
     */
    public static function serializeGallery(
        array $originals,
        array $thumbsByParent,
        string $pagetitle,
        int $previewFileId,
    ): array {
        $validIds = [];
        foreach ($originals as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $validIds[] = $id;
            }
        }
        $effectivePreview = $previewFileId;
        if ($effectivePreview <= 0 || !in_array($effectivePreview, $validIds, true)) {
            $effectivePreview = $validIds[0] ?? 0;
        }

        $items = [];
        foreach ($originals as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $url = (string) ($row['url'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));
            $bundle = $thumbsByParent[$id] ?? [];
            $thumb = trim((string) ($bundle['thumb'] ?? ''));
            $thumbs = self::whitelistThumbs($bundle['thumbs'] ?? []);

            $items[] = [
                'id' => $id,
                'url' => $url,
                'thumb' => $thumb !== '' ? $thumb : $url,
                'thumbs' => $thumbs,
                'name' => $name,
                'description' => (string) ($row['description'] ?? ''),
                'alt' => $name !== '' ? $name : $pagetitle,
                'position' => (int) ($row['position'] ?? 0),
                'is_preview' => $effectivePreview > 0 && $id === $effectivePreview,
            ];
        }

        return $items;
    }

    /**
     * Drop leaked file internals if a plugin mutates images[].
     *
     * @param list<mixed> $images
     * @return list<array<string, mixed>>
     */
    public static function whitelistItems(array $images): array
    {
        $out = [];
        foreach ($images as $item) {
            if (!is_array($item)) {
                continue;
            }

            $clean = [];
            foreach (self::ITEM_KEYS as $key) {
                if (!array_key_exists($key, $item)) {
                    continue;
                }
                if ($key === 'thumbs') {
                    $clean['thumbs'] = self::whitelistThumbs($item['thumbs']);
                    continue;
                }
                $clean[$key] = $item[$key];
            }

            if ($clean !== []) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function whitelistThumbs(mixed $thumbs): array
    {
        if (!is_array($thumbs)) {
            return [];
        }

        $out = [];
        foreach ($thumbs as $size => $url) {
            if ($size === '' || (!is_scalar($url) && $url !== null)) {
                continue;
            }
            $out[(string) $size] = (string) $url;
        }

        return $out;
    }
}
