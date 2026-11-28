<?php

declare(strict_types=1);

namespace MiniShop3\Services\Seo;

use MiniShop3\Model\msResourceSeo;
use MODX\Revolution\modX;

/**
 * Persistence for native SEO overrides on msProduct / msCategory resources (#790).
 *
 * One row per resource (PK = resource_id). Empty incoming values mean "unset / fall back
 * to the public SEO builder defaults": the row is removed (or not created) so the public
 * SEO projection falls through to pagetitle/longtitle/introtext/description and the
 * makeUrl canonical. Non-empty values are sanitized and upserted.
 *
 * Public read path uses {@see PublicSeoService} which overlays non-empty native fields
 * after the TV map and before the msOnGetPublicSeo event.
 */
final class ResourceSeoService
{
    /** @var list<string> */
    public const FIELDS = [
        'title',
        'description',
        'canonical',
        'robots',
        'og_title',
        'og_description',
        'og_image',
    ];

    /** @var list<string> robots directives the manager is allowed to persist */
    public const ROBOTS_ALLOWLIST = [
        'index,follow',
        'noindex,follow',
        'index,nofollow',
        'noindex,nofollow',
    ];

    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function get(int $resourceId): array
    {
        $empty = $this->emptyShape();
        if ($resourceId <= 0) {
            return $empty;
        }

        $row = $this->modx->getObject(msResourceSeo::class, ['resource_id' => $resourceId]);
        if ($row === null) {
            return $empty;
        }

        return $this->normalize($row->toArray());
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, data?: array<string, string|null>, errors?: array<string,string>}
     */
    public function save(int $resourceId, array $data): array
    {
        if ($resourceId <= 0) {
            $this->modx->lexicon->load('minishop3:default');

            return ['ok' => false, 'errors' => ['resource_id' => $this->modx->lexicon('ms3_err_resource_id_required')]];
        }

        $clean = $this->sanitize($data);
        // PUT is a partial update: only fields actually present in the payload are
        // written. Omitted fields keep their stored value instead of being cleared.
        $present = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $present[$field] = $clean[$field];
            }
        }
        $hasValue = array_filter($present, static fn (?string $v) => $v !== null) !== [];

        $row = $this->modx->getObject(msResourceSeo::class, ['resource_id' => $resourceId]);

        // A fully empty payload (or all fields supplied as empty) means "unset":
        // drop the row so the public projection falls through to builder defaults.
        if ($data === [] || (!$hasValue && count($present) === count(self::FIELDS))) {
            if ($row !== null) {
                $row->remove();
            }

            return ['ok' => true, 'data' => $this->emptyShape()];
        }

        if ($row === null) {
            if (!$hasValue) {
                return ['ok' => true, 'data' => $this->emptyShape()];
            }
            $row = $this->modx->newObject(msResourceSeo::class);
            $row->set('resource_id', $resourceId);
        }

        foreach ($present as $field => $value) {
            $row->set($field, $value);
        }

        if (!$row->save()) {
            return ['ok' => false, 'errors' => ['save' => 'Failed to save SEO row']];
        }

        return ['ok' => true, 'data' => $this->normalize($row->toArray())];
    }

    /**
     * @param list<int> $resourceIds
     * @return array<int, array<string, string|null>>
     */
    public function batchByIds(array $resourceIds): array
    {
        $ids = [];
        foreach ($resourceIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $byId = [];
        $q = $this->modx->newQuery(msResourceSeo::class);
        $q->where(['resource_id:IN' => array_keys($ids)]);
        /** @var msResourceSeo $row */
        foreach ($this->modx->getIterator(msResourceSeo::class, $q) as $row) {
            $id = (int) $row->get('resource_id');
            $byId[$id] = $this->normalize($row->toArray());
        }

        $result = [];
        foreach (array_keys($ids) as $id) {
            $result[$id] = $byId[$id] ?? $this->emptyShape();
        }

        return $result;
    }

    public function delete(int $resourceId): bool
    {
        if ($resourceId <= 0) {
            return false;
        }

        return $this->modx->removeCollection(msResourceSeo::class, ['resource_id' => $resourceId]) !== false;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string|null>
     */
    private function sanitize(array $data): array
    {
        $clean = $this->emptyShape();
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            // Arrays/objects cannot be represented as a scalar override value;
            // casting them would emit a warning and persist the literal "Array".
            if ($value !== null && !is_scalar($value)) {
                $clean[$field] = null;
                continue;
            }
            if ($value === null) {
                $clean[$field] = null;
                continue;
            }
            $value = trim($value);
            if ($value === '') {
                $clean[$field] = null;
                continue;
            }

            if ($field === 'robots') {
                $clean[$field] = $this->sanitizeRobots($value);
                continue;
            }

            if ($field === 'canonical' || $field === 'og_image') {
                $clean[$field] = $this->sanitizeUrl($value);
                continue;
            }

            $clean[$field] = $value;
        }

        return $clean;
    }

    private function sanitizeRobots(string $value): ?string
    {
        // Normalize whitespace around commas so "noindex, follow" matches "noindex,follow".
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/\s*,\s*/', ',', $normalized) ?? $value;
        if ($normalized === '') {
            return null;
        }

        return in_array($normalized, self::ROBOTS_ALLOWLIST, true) ? $normalized : null;
    }

    /**
     * Keep http(s) absolute URLs and root-relative paths. Strip protocol-relative and
     * scheme-less junk that could break canonical / og:image on the storefront.
     */
    private function sanitizeUrl(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        // Protocol-relative URLs are not allowed: without this guard they would be
        // rewritten to a local path such as /cdn.example/og.jpg.
        if (str_starts_with($value, '//')) {
            return null;
        }

        // Other schemes (mailto:, javascript:, …) are not allowed.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) === 1) {
            return null;
        }

        // Root-relative or relative path. Trim leading whitespace; keep a single leading slash.
        $value = '/' . ltrim($value, '/');
        return $value !== '/' ? $value : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string|null>
     */
    private function normalize(array $row): array
    {
        $out = $this->emptyShape();
        foreach (self::FIELDS as $field) {
            $value = $row[$field] ?? null;
            if (!is_string($value)) {
                $value = $value === null ? null : (string) $value;
            }
            $value = $value === null ? null : trim($value);
            $out[$field] = $value === '' ? null : $value;
        }

        return $out;
    }

    /**
     * @return array<string, string|null>
     */
    private function emptyShape(): array
    {
        $shape = [];
        foreach (self::FIELDS as $field) {
            $shape[$field] = null;
        }

        return $shape;
    }
}
