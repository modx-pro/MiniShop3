<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

require_once __DIR__ . '/../../../stubs/ModxStub.php';

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\CustomerFieldManager;
use MiniShop3\Utils\StoredTokenHash;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #1058: the facade/resolver create path must not store a session token raw.
 */
final class CustomerFieldManagerHashTest extends TestCase
{
    public function testCreateHashesTokenBeforeSaving(): void
    {
        $modx = new class extends modX {
            /** @var list<array<string, mixed>> */
            public array $created = [];

            public function newObject($className, $fields = [], $cacheFlag = true)
            {
                $this->created[] = $fields;

                return new class extends msCustomer {
                    public function save($cacheFlag = null)
                    {
                        return true;
                    }
                };
            }
        };
        $ms3 = new class extends MiniShop3 {
            public $utils;

            public function __construct()
            {
                $this->utils = new class {
                    public function invokeEvent(string $name, array $params = []): array
                    {
                        return ['success' => true, 'data' => $params];
                    }
                };
            }
        };

        (new CustomerFieldManager($modx, $ms3))->create(new \stdClass(), [
            'email' => 'buyer@example.com',
            'token' => 'session-bearer-token',
        ]);

        self::assertCount(1, $modx->created);
        self::assertSame(
            StoredTokenHash::hash('session-bearer-token'),
            $modx->created[0]['token']
        );
        self::assertNotSame('session-bearer-token', $modx->created[0]['token']);
    }
}
