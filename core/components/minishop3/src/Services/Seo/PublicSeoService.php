<?php

declare(strict_types=1);

namespace MiniShop3\Services\Seo;

use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modX;

/**
 * Attach allowlisted `seo` to public catalog payloads (#567, #703).
 *
 * Flow: PublicSeoBuilder → canonical (makeUrl) → ms3_public_seo_tv_map overlay →
 * whitelist → msOnGetPublicSeo → mirror og text → whitelist.
 *
 * Query flag `include_seo`: default 1 on product/category get; default 0 on list/tree.
 *
 * Event msOnGetPublicSeo (product and category):
 * - Invoked after core build and TV overlay, before the final whitelist.
 * - Params: seo (current array), payload (catalog row), og_type (product|website).
 * - Plugins may set $modx->event->returnedValues['seo'] as an assoc patch; unknown keys
 *   are stripped. og.title / og.description mirror title / description unless explicitly
 *   patched in returnedValues['seo']['og'].
 */
final class PublicSeoService
{
    /** @var array<string, string>|null */
    private ?array $parsedTvMap = null;

    private ?PublicSeoTvMap $tvMap = null;

    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function maybeAttachProduct(array $payload, array $params): array
    {
        return $this->maybeAttach($payload, $params, PublicSeoBuilder::OG_TYPE_PRODUCT);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function maybeAttachCategory(array $payload, array $params): array
    {
        return $this->maybeAttach($payload, $params, PublicSeoBuilder::OG_TYPE_CATEGORY);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function attachSeoToProductList(array $items, array $params): array
    {
        return $this->attachSeoToList($items, $params, PublicSeoBuilder::OG_TYPE_PRODUCT);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function attachSeoToCategoryList(array $items, array $params): array
    {
        return $this->attachSeoToList($items, $params, PublicSeoBuilder::OG_TYPE_CATEGORY);
    }

    /**
     * Attach seo to each node in a category tree (recursive).
     *
     * @param list<array<string, mixed>> $nodes
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function attachSeoToCategoryTree(array $nodes, array $params): array
    {
        if (!CatalogQuery::resolveBool($params, 'include_seo', false)) {
            return $nodes;
        }

        return $this->attachSeoToCategoryTreeNodes($nodes, $params);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function attachSeoToCategoryTreeNodes(array $nodes, array $params): array
    {
        $result = [];
        foreach ($nodes as $node) {
            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = $this->attachSeoToCategoryTreeNodes($node['children'], $params);
            }
            $result[] = $this->attach($node, $params, PublicSeoBuilder::OG_TYPE_CATEGORY);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function maybeAttach(array $payload, array $params, string $ogType): array
    {
        if (!CatalogQuery::resolveBool($params, 'include_seo', true)) {
            return $payload;
        }

        return $this->attach($payload, $params, $ogType);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function attach(array $payload, array $params, string $ogType): array
    {
        $payload['seo'] = $this->build($payload, $params, $ogType);

        return $payload;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function attachSeoToList(array $items, array $params, string $ogType): array
    {
        if (!CatalogQuery::resolveBool($params, 'include_seo', false)) {
            return $items;
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = $this->attach($item, $params, $ogType);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function build(array $payload, array $params, string $ogType): array
    {
        $siteUrl = $this->siteUrl($payload, $params);
        $seo = PublicSeoBuilder::build($payload, $siteUrl, $ogType);
        $seo['canonical'] = $this->resolveCanonical($payload, $params, $siteUrl);

        $resourceId = (int) ($payload['id'] ?? 0);
        $tvMap = $this->parsedTvMap();
        $tvExplicitPatch = null;
        if ($resourceId > 0 && $tvMap !== []) {
            [$seo, $appliedKeys] = $this->tvMap()->overlay($seo, $resourceId, $tvMap, $siteUrl);
            if ($appliedKeys !== []) {
                $tvExplicitPatch = self::explicitPatchFromAppliedKeys($appliedKeys, $seo);
            }
        }

        $seo = PublicSeoBuilder::whitelist($seo);

        $event = EventGate::invokeRaw($this->modx, 'msOnGetPublicSeo', [
            'seo' => $seo,
            'payload' => $payload,
            'og_type' => $ogType,
        ]);
        $patch = $event['returnedValues']['seo'] ?? null;
        $seo = EventGate::applyReturnedArray($seo, $event['returnedValues'], 'seo');

        return PublicSeoBuilder::whitelist(self::mirrorOgText($seo, self::mergeSeoPatches($tvExplicitPatch, $patch)));
    }

    /**
     * Prefer MODX makeUrl (friendly_urls=0 safe); fallback to site_url + uri.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     */
    private function resolveCanonical(array $payload, array $params, string $siteUrl): string
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id > 0) {
            $url = trim((string) $this->modx->makeUrl(
                $id,
                $this->resolveContextKey($payload, $params),
                '',
                'full',
            ));
            if ($url !== '') {
                return $url;
            }
        }

        return PublicSeoBuilder::absoluteUrl($siteUrl, (string) ($payload['uri'] ?? ''));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     */
    private function siteUrl(array $payload, array $params): string
    {
        $context = $this->modx->getContext($this->resolveContextKey($payload, $params));
        if (is_object($context) && method_exists($context, 'getOption')) {
            $url = trim((string) $context->getOption('site_url'));
            if ($url !== '') {
                return $url;
            }
        }

        return (string) $this->modx->getOption('site_url', null, '');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     */
    private function resolveContextKey(array $payload, array $params): string
    {
        $fromPayload = trim((string) ($payload['context_key'] ?? ''));

        return CatalogQuery::resolveContext($params, $fromPayload !== '' ? $fromPayload : 'web');
    }

    /**
     * @return array<string, string>
     */
    private function parsedTvMap(): array
    {
        if ($this->parsedTvMap === null) {
            $this->parsedTvMap = PublicSeoTvMap::parseSettingValue(
                $this->modx->getOption(PublicSeoTvMap::SETTING_KEY, null, ''),
            );
        }

        return $this->parsedTvMap;
    }

    private function tvMap(): PublicSeoTvMap
    {
        if ($this->tvMap === null) {
            $this->tvMap = new PublicSeoTvMap($this->modx);
        }

        return $this->tvMap;
    }

    /**
     * Build a synthetic plugin patch so mirrorOgText treats TV-written og keys as explicit.
     *
     * @param list<string> $appliedKeys
     * @param array<string, mixed> $seo
     * @return array<string, mixed>
     */
    private static function explicitPatchFromAppliedKeys(array $appliedKeys, array $seo): array
    {
        $patch = [];
        foreach ($appliedKeys as $key) {
            if (!str_starts_with($key, 'og.')) {
                if (array_key_exists($key, $seo)) {
                    $patch[$key] = $seo[$key];
                }
                continue;
            }

            $ogKey = substr($key, 3);
            $og = isset($seo['og']) && is_array($seo['og']) ? $seo['og'] : [];
            if (array_key_exists($ogKey, $og)) {
                $patch['og'] ??= [];
                $patch['og'][$ogKey] = $og[$ogKey];
            }
        }

        return $patch;
    }

    /**
     * @param array<string, mixed>|null $base
     * @return array<string, mixed>|null
     */
    private static function mergeSeoPatches(?array $base, mixed $overlay): ?array
    {
        if (!is_array($overlay) || $overlay === []) {
            return $base;
        }
        if ($base === null || $base === []) {
            return $overlay;
        }

        $merged = $base;
        foreach ($overlay as $key => $value) {
            if ($key === 'og' && is_array($value)) {
                $merged['og'] = array_merge(
                    is_array($merged['og'] ?? null) ? $merged['og'] : [],
                    $value,
                );
                continue;
            }
            $merged[$key] = $value;
        }

        return $merged;
    }

    /**
     * Keep og.title / og.description in sync with title / description unless the plugin
     * explicitly patched those og keys.
     *
     * @param array<string, mixed> $seo
     * @return array<string, mixed>
     */
    private static function mirrorOgText(array $seo, mixed $patch): array
    {
        $ogPatch = is_array($patch) && isset($patch['og']) && is_array($patch['og']) ? $patch['og'] : [];
        $og = isset($seo['og']) && is_array($seo['og']) ? $seo['og'] : [];
        if (!array_key_exists('title', $ogPatch)) {
            $og['title'] = (string) ($seo['title'] ?? '');
        }
        if (!array_key_exists('description', $ogPatch)) {
            $og['description'] = (string) ($seo['description'] ?? '');
        }
        $seo['og'] = $og;

        return $seo;
    }
}
