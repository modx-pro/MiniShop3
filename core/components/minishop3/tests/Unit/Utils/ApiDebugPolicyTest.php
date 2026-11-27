<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Utils\ApiDebugPolicy;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #931: exception details require ms3_api_debug and the same manager identity
 * as the mgr context token — global debug or a web user must never be enough.
 */
final class ApiDebugPolicyTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
    }

    public function testGlobalDebugSettingNeverExposesDetails(): void
    {
        self::assertFalse(ApiDebugPolicy::allowed($this->modx(false, 7, true)));
    }

    public function testAnonymousClientNeverSeesDetailsEvenWithApiDebugEnabled(): void
    {
        self::assertFalse(ApiDebugPolicy::allowed($this->modx(true, null, true)));
    }

    public function testUnsetSettingNeverExposesDetails(): void
    {
        self::assertFalse(ApiDebugPolicy::allowed($this->modx(null, 7, false)));
    }

    public function testWebUserWithMgrValidatedFlagDoesNotSeeDetails(): void
    {
        $_SESSION['mgrValidated'] = 1;

        self::assertFalse(ApiDebugPolicy::allowed($this->modx(true, 7, false)));
    }

    public function testDifferentManagerIdentityDoesNotSeeDetails(): void
    {
        $_SESSION['modx.user.contextTokens'] = ['mgr' => 99];

        self::assertFalse(ApiDebugPolicy::allowed($this->modx(true, 7, false)));
    }

    public function testManagerSeesDetailsOnlyWithApiDebugEnabled(): void
    {
        $_SESSION['modx.user.contextTokens'] = ['mgr' => 7];

        self::assertTrue(ApiDebugPolicy::allowed($this->modx(true, 7, false)));
        self::assertFalse(ApiDebugPolicy::allowed($this->modx(false, 7, false)));
    }

    /**
     * @param bool|null $apiDebug null means the setting is not configured
     * @param int|null $userId null means no authenticated user
     */
    private function modx(?bool $apiDebug, ?int $userId, bool $globalDebug): modX
    {
        $modx = new modX();
        $options = ['debug' => $globalDebug];
        if ($apiDebug !== null) {
            $options['ms3_api_debug'] = $apiDebug;
        }
        $modx->optionOverrides = $options;
        $modx->user = $userId === null ? null : new class ($userId) {
            public function __construct(private int $id)
            {
            }

            public function get($key)
            {
                return $key === 'id' ? $this->id : null;
            }

            public function isAuthenticated(string $context = ''): bool
            {
                return $context === 'mgr';
            }

            public function hasSessionContext(string $context = ''): bool
            {
                return $context === 'mgr';
            }
        };

        return $modx;
    }
}
