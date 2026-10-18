<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Processors;

use MiniShop3\Processors\Customer\GetList as CustomerGetList;
use MiniShop3\Processors\Customer\GetListCombobox;
use MiniShop3\Processors\System\User\GetList as UserGetList;
use MiniShop3\Services\Customer\CustomerManagerFields;
use PHPUnit\Framework\TestCase;
use xPDO\Om\xPDOObject;

require_once dirname(__DIR__, 2) . '/stubs/ModxProcessorBasesStub.php';

/**
 * #908: manager-side customer/user processors must return an allowlisted set of fields,
 * with credential columns out by construction.
 */
final class SecretFieldsTest extends TestCase
{
    private const CUSTOMER_SECRETS = ['password', 'token'];
    private const USER_SECRETS = ['password', 'cachepwd', 'salt', 'remote_key', 'remote_data'];

    /**
     * @param array<string, mixed> $fields
     */
    private function object(array $fields): xPDOObject
    {
        return new class ($fields) extends xPDOObject {
            /** @param array<string, mixed> $fields */
            public function __construct(private array $fields)
            {
            }

            /** @return array<string, mixed> */
            public function toArray(
                $keyPrefix = '',
                $rawValues = false,
                $excludeLazy = false,
                $includeRelated = false
            ) {
                return $this->fields;
            }
        };
    }

    /** @return array<string, mixed> */
    private function customerRow(): array
    {
        return [
            'id' => 7,
            'user_id' => 2,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'email' => 'ivan@example.com',
            'phone' => '+70000000000',
            'token' => 'a1b2c3-session-token',
            'password' => '$2y$10$hashhashhashhashhash',
            'is_active' => true,
            'privacy_ip' => '203.0.113.9',
        ];
    }

    /** @return array<string, mixed> */
    private function userRow(): array
    {
        return [
            'id' => 3,
            'username' => 'manager',
            'fullname' => 'Менеджер',
            'password' => '$2y$10$userhashuserhashuser',
            'cachepwd' => 'cached-reset-hash',
            'salt' => 'deadbeefsalt',
            'remote_key' => 'sso-key',
            'remote_data' => '{"provider":"sso"}',
            'hash_class' => 'hashing.modPBKDF2',
            'active' => 1,
        ];
    }

    public function testCustomerAllowlistKeepsBusinessFieldsAndDropsTheRest(): void
    {
        $picked = CustomerManagerFields::pick($this->customerRow());

        foreach (self::CUSTOMER_SECRETS as $secret) {
            self::assertArrayNotHasKey($secret, $picked);
        }
        self::assertSame('ivan@example.com', $picked['email']);
        self::assertSame(7, $picked['id']);
    }

    public function testCustomerAllowlistIsFailClosedForUnknownColumns(): void
    {
        $picked = CustomerManagerFields::pick(['id' => 1, 'recovery_code' => 'secret-code']);

        self::assertSame(['id' => 1], $picked, 'a column added later must not pass until allowlisted');
    }

    public function testExcludedColumnsListsEverythingOutsideTheAllowlist(): void
    {
        $meta = [
            'id' => [], 'email' => [], 'password' => [], 'token' => [], 'recovery_code' => [],
        ];

        $excluded = CustomerManagerFields::excludedColumns($meta);

        self::assertContains('password', $excluded);
        self::assertContains('token', $excluded);
        self::assertContains('recovery_code', $excluded);
        self::assertNotContains('id', $excluded);
        self::assertNotContains('email', $excluded);
    }

    public function testComboProcessorHidesCredentialsWithoutCombo(): void
    {
        $processor = new class extends GetListCombobox {
            public function __construct()
            {
            }

            public function getProperty($key, $default = null)
            {
                return $default;
            }
        };

        $row = $processor->prepareRow($this->object($this->customerRow()));

        foreach (self::CUSTOMER_SECRETS as $secret) {
            self::assertArrayNotHasKey($secret, $row);
        }
        self::assertSame('ivan@example.com', $row['email']);
    }

    public function testComboProcessorStillReturnsIdAndFullname(): void
    {
        $processor = new class extends GetListCombobox {
            public function __construct()
            {
            }

            public function getProperty($key, $default = null)
            {
                return $key === 'combo' ? 1 : $default;
            }
        };

        self::assertSame(
            ['id' => 7, 'fullname' => 'Иван Петров'],
            $processor->prepareRow($this->object($this->customerRow()))
        );
    }

    public function testCustomerGridRowHidesCredentialsAndKeepsActions(): void
    {
        $processor = new class extends CustomerGetList {
            /** @var object */
            public $modx;

            public function __construct()
            {
                $this->modx = new class {
                    public function lexicon(string $key): string
                    {
                        return $key;
                    }
                };
            }

            public function getProperty($key, $default = null)
            {
                return $default;
            }
        };

        $row = $processor->prepareArray($this->customerRow());

        foreach (self::CUSTOMER_SECRETS as $secret) {
            self::assertArrayNotHasKey($secret, $row, 'grid page must not carry credentials');
        }
        self::assertSame('ivan@example.com', $row['email']);
        self::assertArrayHasKey('actions', $row, 'grid actions must survive the allowlist');
        self::assertCount(2, $row['actions']);
    }

    public function testUserRowHidesCredentialsWithoutCombo(): void
    {
        $processor = new class extends UserGetList {
            public function __construct()
            {
            }

            public function getProperty($key, $default = null)
            {
                return $default;
            }
        };

        $row = $processor->prepareRow($this->object($this->userRow()));

        foreach (self::USER_SECRETS as $secret) {
            self::assertArrayNotHasKey($secret, $row, "{$secret} must never leave the processor");
        }
        self::assertSame('manager', $row['username']);
        self::assertSame(1, $row['active']);
        self::assertArrayNotHasKey('hash_class', $row, 'allowlist is fail-closed');
    }

    public function testUserComboBranchStillReturnsIdUsernameFullname(): void
    {
        $processor = new class extends UserGetList {
            public function __construct()
            {
            }

            public function getProperty($key, $default = null)
            {
                return $key === 'combo' ? 1 : $default;
            }
        };

        self::assertSame(
            ['id' => 3, 'username' => 'manager', 'fullname' => 'Менеджер'],
            $processor->prepareRow($this->object($this->userRow()))
        );
    }

    public function testUserComboFallsBackToUsernameWhenFullnameEmpty(): void
    {
        $processor = new class extends UserGetList {
            public function __construct()
            {
            }

            public function getProperty($key, $default = null)
            {
                return $key === 'combo' ? 1 : $default;
            }
        };

        $fields = $this->userRow();
        $fields['fullname'] = '';

        self::assertSame('manager', $processor->prepareRow($this->object($fields))['fullname']);
    }
}
