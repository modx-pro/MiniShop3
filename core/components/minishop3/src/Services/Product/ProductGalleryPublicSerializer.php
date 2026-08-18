<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

/**
 * Public storefront gallery DTO (#566).
 *
 * There is no DB `alt` column: alt is derived from name, then product pagetitle.
 * include_images=1 with no files yields `images: []` (key present, empty list).
 */
final class ProductGalleryPublicSerializer
{
    public const MAX_IMAGES = 50;

    /** @var list<string> */
    public const ITEM_KEYS = [
        'id',
        'url',
        'thumb',
        'name',
        'description',
        'alt',
        'position',
        'is_preview',
    ];

    /**
     * @param list<array<string, mixed>> $originals Top-level files (already filtered/sorted/capped)
     * @param array<int, string> $thumbByParentId parent file id => thumb url
     * @return list<array{
     *     id: int,
     *     url: string,
     *     thumb: string,
     *     name: string,
     *     description: string,
     *     alt: string,
     *     position: int,
     *     is_preview: bool
     * }>
     */
    public static function serializeGallery(
        array $originals,
        array $thumbByParentId,
        string $pagetitle,
        int $previewFileId,
    ): array {
        $items = [];
        foreach ($originals as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $url = (string) ($row['url'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));

            $items[] = [
                'id' => $id,
                'url' => $url,
                'thumb' => $thumbByParentId[$id] ?? $url,
                'name' => $name,
                'description' => (string) ($row['description'] ?? ''),
                'alt' => $name !== '' ? $name : $pagetitle,
                'position' => (int) ($row['position'] ?? 0),
                'is_preview' => $previewFileId > 0 && $id === $previewFileId,
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
                if (array_key_exists($key, $item)) {
                    $clean[$key] = $item[$key];
                }
            }

            if ($clean !== []) {
                $out[] = $clean;
            }
        }

        return $out;
    }
}
