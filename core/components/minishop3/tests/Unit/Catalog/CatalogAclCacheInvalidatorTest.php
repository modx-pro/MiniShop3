<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Catalog;

use MiniShop3\Services\Catalog\CatalogAclCacheInvalidator;
use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use MiniShop3\Tests\Stubs\CatalogAclInvalidatorModxStub;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/stubs/CatalogAclModxStub.php';

final class CatalogAclCacheInvalidatorTest extends TestCase
{
    public function testInvalidateRefreshesResourceCacheAndClearsFacetsWhenEnabled(): void
    {
        $refreshProviders = null;
        $facetCleared = false;

        $cacheManager = $this->captureRefreshCacheManager($refreshProviders);
        $services = $this->facetsServices($facetCleared);

        $modx = new CatalogAclInvalidatorModxStub($cacheManager, $services, [
            'access_resource_group_enabled' => true,
            CatalogResourceGroupVisibility::SETTING_KEY => true,
        ], ['web', 'shop']);

        (new CatalogAclCacheInvalidator($modx))->invalidate();

        self::assertSame(['resource' => ['contexts' => ['shop', 'web']]], $refreshProviders);
        self::assertTrue($facetCleared);
    }

    public function testInvalidateIsNoOpWhenSettingsDisabled(): void
    {
        $refreshCalled = false;
        $cacheManager = new class($refreshCalled) {
            public function __construct(private bool &$refreshCalled)
            {
            }

            public function refresh(array $providers = [], array &$results = []): bool
            {
                $this->refreshCalled = true;

                return true;
            }
        };

        $modx = new CatalogAclInvalidatorModxStub(
            $cacheManager,
            new class {
                public function has(string $key): bool
                {
                    return false;
                }
            },
            [CatalogResourceGroupVisibility::SETTING_KEY => false],
            [],
        );

        (new CatalogAclCacheInvalidator($modx))->invalidate();

        self::assertFalse($refreshCalled);
    }

    public function testInvalidateRunsOncePerInstance(): void
    {
        $refreshCount = 0;
        $facetClearCount = 0;

        $cacheManager = new class($refreshCount) {
            public function __construct(private int &$refreshCount)
            {
            }

            public function refresh(array $providers = [], array &$results = []): bool
            {
                ++$this->refreshCount;

                return true;
            }
        };

        $services = $this->countingFacetsServices($facetClearCount);
        $modx = new CatalogAclInvalidatorModxStub(
            $cacheManager,
            $services,
            ['access_resource_group_enabled' => true, CatalogResourceGroupVisibility::SETTING_KEY => true],
            ['web'],
        );

        $invalidator = new CatalogAclCacheInvalidator($modx);
        $invalidator->invalidate();
        $invalidator->invalidate();

        self::assertSame(1, $refreshCount);
        self::assertSame(1, $facetClearCount);
    }

    public function testSecondInstanceStillInvalidatesInSameProcess(): void
    {
        $refreshCount = 0;
        $cacheManager = new class($refreshCount) {
            public function __construct(private int &$refreshCount)
            {
            }

            public function refresh(array $providers = [], array &$results = []): bool
            {
                ++$this->refreshCount;

                return true;
            }
        };

        $facetClearCount = 0;
        $services = $this->countingFacetsServices($facetClearCount);
        $options = [
            'access_resource_group_enabled' => true,
            CatalogResourceGroupVisibility::SETTING_KEY => true,
        ];

        $first = new CatalogAclCacheInvalidator(
            new CatalogAclInvalidatorModxStub($cacheManager, $services, $options, ['web']),
        );
        $second = new CatalogAclCacheInvalidator(
            new CatalogAclInvalidatorModxStub($cacheManager, $services, $options, ['web']),
        );

        $first->invalidate();
        $second->invalidate();

        self::assertSame(2, $refreshCount);
        self::assertSame(2, $facetClearCount);
    }

    public function testEmptyContextListStillRefreshesResourceCache(): void
    {
        $refreshProviders = null;
        $cacheManager = $this->captureRefreshCacheManager($refreshProviders);
        $facetCleared = false;
        $services = $this->facetsServices($facetCleared);

        $modx = new CatalogAclInvalidatorModxStub(
            $cacheManager,
            $services,
            ['access_resource_group_enabled' => true, CatalogResourceGroupVisibility::SETTING_KEY => true],
            [],
        );

        (new CatalogAclCacheInvalidator($modx))->invalidate();

        self::assertSame(['resource' => []], $refreshProviders);
        self::assertTrue($facetCleared);
    }

    public function testScheduleRegistersOnceAndMarksScheduled(): void
    {
        $modx = new CatalogAclInvalidatorModxStub(
            new class {
                public function refresh(array $providers = [], array &$results = []): bool
                {
                    return true;
                }
            },
            new class {
                public function has(string $key): bool
                {
                    return false;
                }
            },
            ['access_resource_group_enabled' => true, CatalogResourceGroupVisibility::SETTING_KEY => true],
            ['web'],
        );

        $invalidator = new CatalogAclCacheInvalidator($modx);
        $invalidator->schedule();
        $invalidator->schedule();

        self::assertTrue($invalidator->wasScheduledForTests());
        self::assertFalse($invalidator->wasInvalidatedForTests());
    }

    /**
     * @param array<string, mixed>|null $refreshProviders
     */
    private function captureRefreshCacheManager(?array &$refreshProviders): object
    {
        return new class($refreshProviders) {
            public function __construct(public ?array &$refreshProviders)
            {
            }

            public function refresh(array $providers = [], array &$results = []): bool
            {
                $this->refreshProviders = $providers;

                return true;
            }
        };
    }

    private function facetsServices(bool &$facetCleared): object
    {
        $facets = new class($facetCleared) {
            public function __construct(private bool &$facetCleared)
            {
            }

            public function clearCache(): bool
            {
                $this->facetCleared = true;

                return true;
            }
        };

        return new class($facets) {
            public function __construct(private object $facets)
            {
            }

            public function has(string $key): bool
            {
                return $key === 'ms3_product_facets';
            }

            public function get(string $key): object
            {
                return $this->facets;
            }
        };
    }

    private function countingFacetsServices(int &$facetClearCount): object
    {
        $facets = new class($facetClearCount) {
            public function __construct(private int &$facetClearCount)
            {
            }

            public function clearCache(): bool
            {
                ++$this->facetClearCount;

                return true;
            }
        };

        return new class($facets) {
            public function __construct(private object $facets)
            {
            }

            public function has(string $key): bool
            {
                return true;
            }

            public function get(string $key): object
            {
                return $this->facets;
            }
        };
    }
}
