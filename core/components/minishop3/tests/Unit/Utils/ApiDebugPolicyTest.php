<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Utils\ApiDebugPolicy;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #931: exception details require both ms3_api_debug and a manager session.
 */
final class ApiDebugPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
    }

    public function testGlobalDebugSettingNeverExposesDetails(): void
    {
        $modx = $this->modx(false, true, true);

        self::assertFalse(ApiDebugPolicy::allowed($modx));
    }

    public function testAnonymousClientNeverSeesDetailsEvenWithApiDebugEnabled(): void
    {
        $modx = $this->modx(true, false, true);

        self::assertFalse(ApiDebugPolicy::allowed($modx));
    }

    public function testAbsentUserNeverSeesDetails(): void
    {
        $modx = $this->modx(true, null, false);

        self::assertFalse(ApiDebugPolicy::allowed($modx));
    }

    public function testManagerSeesDetailsOnlyWithApiDebugEnabled(): void
    {
        self::assertTrue(ApiDebugPolicy::allowed($this->modx(true, true, false)));
        self::assertFalse(ApiDebugPolicy::allowed($this->modx(false, true, false)));
    }

    /**
     * @param bool|null $manager null means no authenticated user
     */
    private function modx(bool $apiDebug, ?bool $manager, bool $globalDebug): modX
    {
        $modx = new modX();
        $modx->optionOverrides = [
            'ms3_api_debug' => $apiDebug,
            'debug' => $globalDebug,
        ];
        $modx->user = $manager === null ? null : new class ($manager) {
            public function __construct(private bool $manager)
            {
            }

            public function isAuthenticated(string $context = ''): bool
            {
                return $this->manager && $context === 'mgr';
            }
        };

        return $modx;
    }
}
