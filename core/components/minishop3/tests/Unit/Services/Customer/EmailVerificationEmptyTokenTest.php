<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

use MiniShop3\Services\Customer\EmailVerificationService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #927 review: the legacy `token` column is empty for all new rows and no
 * longer UNIQUE, so a blank input must not resolve an arbitrary row.
 */
final class EmailVerificationEmptyTokenTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testEmptyTokenIsRejectedBeforeAnyLookup(): void
    {
        $modx = new class extends modX {
            public int $getObjectCalls = 0;

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                $this->getObjectCalls++;

                return new class {
                    public function get($key)
                    {
                        return $key === 'expires_at' ? date('Y-m-d H:i:s', time() + 3600) : null;
                    }
                };
            }
        };

        self::assertNull((new EmailVerificationService($modx))->verifyToken(''));
        self::assertSame(0, $modx->getObjectCalls);
    }
}
