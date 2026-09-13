<?php

declare(strict_types=1);

namespace MiniShop3\Services\Seo;

use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modX;

/**
 * Attach allowlisted `seo` to a public catalog payload (#567).
 *
 * Optional enrichment: plugins on msOnGetPublicSeo may set
 * $modx->event->returnedValues['seo'] (assoc patch). Core has no SEO Extra.
 */
final class PublicSeoService
{
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
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function maybeAttach(array $payload, array $params, string $ogType): array
    {
        if (!CatalogQuery::resolveBool($params, 'include_seo', true)) {
            return $payload;
        }

        $payload['seo'] = $this->build($payload, $params, $ogType);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function build(array $payload, array $params, string $ogType): array
    {
        $seo = PublicSeoBuilder::build($payload, $this->siteUrl($payload, $params), $ogType);

        $event = EventGate::invokeRaw($this->modx, 'msOnGetPublicSeo', [
            'seo' => $seo,
            'payload' => $payload,
            'og_type' => $ogType,
        ]);
        $patch = $event['returnedValues']['seo'] ?? null;
        $seo = EventGate::applyReturnedArray($seo, $event['returnedValues'], 'seo');

        return PublicSeoBuilder::whitelist(self::mirrorOgText($seo, $patch));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     */
    private function siteUrl(array $payload, array $params): string
    {
        $fromPayload = trim((string) ($payload['context_key'] ?? ''));
        $contextKey = CatalogQuery::resolveContext($params, $fromPayload !== '' ? $fromPayload : 'web');
        $context = $this->modx->getContext($contextKey);
        if (is_object($context) && method_exists($context, 'getOption')) {
            $url = trim((string) $context->getOption('site_url'));
            if ($url !== '') {
                return $url;
            }
        }

        return (string) $this->modx->getOption('site_url', null, '');
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
