<?php

declare(strict_types=1);

namespace MiniShop3\Services\Seo;

use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modX;

/**
 * Attach allowlisted `seo` to public catalog payloads (#567, #703, #790).
 *
 * Flow: PublicSeoBuilder → canonical (makeUrl) → ms3_public_seo_tv_map overlay →
 * native ms3_resource_seo overlay → whitelist → msOnGetPublicSeo → mirror og text →
 * whitelist.
 *
 * Query flag `include_seo`: default 1 on product/category get; default 0 on list/tree.
 *
 * Event msOnGetPublicSeo (product and category):
 * - Invoked after core build, TV overlay, and native overlay, before the final whitelist.
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

    /** DB column => public seo key for native overlays (#790). */
    private const NATIVE_TO_PUBLIC = [
        'title' => 'title',
        'description' => 'description',
        'canonical' => 'canonical',
        'robots' => 'robots',
        'og_title' => 'og.title',
        'og_description' => 'og.description',
        'og_image' => 'og.image',
    ];

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
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function attachSeoToList(array $items, array $params, string $ogType): array
    {
        if (!CatalogQuery::resolveBool($params, 'include_seo', false)) {
            return $items;
        }

        $ids = [];
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $nativeBatch = $this->nativeSeoBatch($ids);

        $result = [];
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            $result[] = $this->attach(
                $item,
                $params,
                $ogType,
                $nativeBatch[$id] ?? null,
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     * @param array<string, string|null>|null $nativePreload
     * @return array<string, mixed>
     */
    private function attach(
        array $payload,
        array $params,
        string $ogType,
        ?array $nativePreload = null,
    ): array {
        $payload['seo'] = $this->build($payload, $params, $ogType, $nativePreload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     * @param array<string, string|null>|null $nativePreload
     * @return array<string, mixed>
     */
    private function build(
        array $payload,
        array $params,
        string $ogType,
        ?array $nativePreload = null,
    ): array {
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

        $nativeExplicitPatch = null;
        [$seo, $nativeExplicitPatch] = $this->overlayNativeSeo(
            $seo,
            $resourceId,
            $siteUrl,
            $nativePreload,
        );

        $seo = PublicSeoBuilder::whitelist($seo);

        $event = EventGate::invokeRaw($this->modx, 'msOnGetPublicSeo', [
            'seo' => $seo,
            'payload' => $payload,
            'og_type' => $ogType,
        ]);
        $patch = $event['returnedValues']['seo'] ?? null;
        $seo = EventGate::applyReturnedArray($seo, $event['returnedValues'], 'seo');

        return PublicSeoBuilder::whitelist(
            self::mirrorOgText(
                $seo,
                self::mergeSeoPatches(
                    self::mergeSeoPatches($tvExplicitPatch, $nativeExplicitPatch),
                    $patch,
                ),
            ),
        );
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
     * @param list<int> $resourceIds
     * @return array<int, array<string, string|null>>
     */
    private function nativeSeoBatch(array $resourceIds): array
    {
        if ($resourceIds === [] || !$this->modx->services->has('ms3_resource_seo')) {
            return [];
        }

        try {
            /** @var ResourceSeoService $service */
            $service = $this->modx->services->get('ms3_resource_seo');

            return $service->batchByIds($resourceIds);
        } catch (\Throwable $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[PublicSeoService] native seo batch failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Overlay non-empty native fields via the same applyValues pipeline as the TV map.
     *
     * @param array<string, mixed> $seo
     * @param array<string, string|null>|null $nativePreload
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private function overlayNativeSeo(
        array $seo,
        int $resourceId,
        string $siteUrl,
        ?array $nativePreload = null,
    ): array {
        $native = $nativePreload;
        if ($native === null) {
            $native = $this->loadNativeSeo($resourceId);
        }
        if ($native === null) {
            return [$seo, null];
        }

        $values = [];
        foreach (self::NATIVE_TO_PUBLIC as $dbField => $seoKey) {
            $value = $native[$dbField] ?? null;
            if ($value !== null && $value !== '') {
                $values[$seoKey] = $value;
            }
        }
        if ($values === []) {
            return [$seo, null];
        }

        [$seo, $appliedKeys] = PublicSeoTvMap::applyValues($seo, $values, $siteUrl);
        if ($appliedKeys === []) {
            return [$seo, null];
        }

        return [$seo, self::explicitPatchFromAppliedKeys($appliedKeys, $seo)];
    }

    /**
     * @return array<string, string|null>|null
     */
    private function loadNativeSeo(int $resourceId): ?array
    {
        if ($resourceId <= 0 || !$this->modx->services->has('ms3_resource_seo')) {
            return null;
        }

        try {
            /** @var ResourceSeoService $service */
            $service = $this->modx->services->get('ms3_resource_seo');

            return $service->get($resourceId);
        } catch (\Throwable $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[PublicSeoService] native seo load failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Build a synthetic plugin patch so mirrorOgText treats TV/native-written og keys as explicit.
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
