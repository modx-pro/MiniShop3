<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

use MODX\Revolution\modContext;
use MODX\Revolution\modX;

/**
 * Invalidates storefront resource/page cache and MiniShop3 facet cache after ACL or
 * resource-group membership mutations (#717, variant A).
 *
 * Runs at most once per HTTP request (this service instance). Active when
 * {@see CatalogResourceGroupVisibility} is enabled.
 *
 * Residual gaps (setting lexicon): ACL written outside MODX processors, external CDN.
 */
final class CatalogAclCacheInvalidator
{
    private bool $scheduled = false;

    private bool $invalidated = false;

    private ?CatalogResourceGroupVisibility $visibility = null;

    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * Defer {@see invalidate()} until after the current processor finishes.
     */
    public function schedule(): void
    {
        if ($this->scheduled || $this->invalidated || !$this->visibility()->isEnabled()) {
            return;
        }

        $this->scheduled = true;
        register_shutdown_function(function (): void {
            $this->invalidate();
        });
    }

    /**
     * Clear targeted MODX resource cache partitions and ms3/facets immediately.
     */
    public function invalidate(): void
    {
        if ($this->invalidated) {
            return;
        }

        if (!$this->visibility()->isEnabled()) {
            return;
        }

        $this->invalidated = true;

        $cacheManager = $this->modx->cacheManager;
        if (!is_object($cacheManager)) {
            return;
        }

        $contexts = $this->storefrontContextKeys();
        if ($contexts !== []) {
            $cacheManager->refresh(['resource' => ['contexts' => $contexts]]);
        } else {
            // Fail closed for page cache: empty context list must not skip refresh (#717).
            $cacheManager->refresh(['resource' => []]);
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                '[MiniShop3] CatalogAclCacheInvalidator: storefront context list empty; refreshed all resource cache',
            );
        }

        if ($this->modx->services->has('ms3_product_facets')) {
            $facets = $this->modx->services->get('ms3_product_facets');
            if (is_object($facets) && method_exists($facets, 'clearCache')) {
                $facets->clearCache();
            }
        }
    }

    /** @internal PHPUnit only */
    public function wasScheduledForTests(): bool
    {
        return $this->scheduled;
    }

    /** @internal PHPUnit only */
    public function wasInvalidatedForTests(): bool
    {
        return $this->invalidated;
    }

    private function visibility(): CatalogResourceGroupVisibility
    {
        return $this->visibility ??= new CatalogResourceGroupVisibility($this->modx);
    }

    /**
     * @return list<string>
     */
    private function storefrontContextKeys(): array
    {
        $query = $this->modx->newQuery(modContext::class);
        $query->select($this->modx->escape('key'));
        $query->where(['key:!=' => 'mgr']);

        if (!$query->prepare() || !$query->stmt->execute()) {
            return [];
        }

        $keys = array_values(array_filter(
            array_map(static fn ($key): string => trim((string) $key), $query->stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []),
            static fn (string $key): bool => $key !== '',
        ));
        sort($keys);

        return $keys;
    }
}
