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
 * with credential columns out by construction, and the site's own msExtraField columns
 * travelling with that allowlist.
 */
final class SecretFieldsTest extends TestCase
{
    private const CUSTOMER_SECRETS = ['password', 'token'];
    private const USER_SECRETS = ['password', 'cachepwd', 'salt', 'remote_key', 'remote_data'];
    private const EXTRA_FIELD = 'ms3_extra_note';

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

    /**
     * Combobox processor with the extra-field lookup pre-seeded, so prepareRow() never
     * reaches CustomerExtraFieldRegistry (which needs a live modX).
     *
     * @param list<string> $extraFieldKeys
     */
    private function combobox(array $extraFieldKeys = [], bool $combo = false): GetListCombobox
    {
        return new class ($extraFieldKeys, $combo) extends GetListCombobox {
            /** @param list<string> $extraFieldKeys */
            public function __construct(array $extraFieldKeys, private bool $combo)
            {
                $this->ms3CustomerExtraFieldKeys = $extraFieldKeys;
            }

            public function getProperty($key, $default = null)
            {
                return $key === 'combo' && $this->combo ? 1 : $default;
            }
        };
    }

    /**
     * @param list<string> $extraFieldKeys
     */
    private function grid(array $extraFieldKeys = []): CustomerGetList
    {
        return new class ($extraFieldKeys) extends CustomerGetList {
            /** @var object */
            public $modx;

            /** @param list<string> $extraFieldKeys */
            public function __construct(array $extraFieldKeys)
            {
                $this->ms3CustomerExtraFieldKeys = $extraFieldKeys;
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
    }

    private function userProcessor(bool $combo = false): UserGetList
    {
        return new class ($combo) extends UserGetList {
            public function __construct(private bool $combo)
            {
            }

            public function getProperty($key, $default = null)
            {
                return $key === 'combo' && $this->combo ? 1 : $default;
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
            self::EXTRA_FIELD => 'заметка магазина',
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

    public function testRegisteredExtraFieldTravelsWithTheAllowlist(): void
    {
        $row = $this->customerRow();

        self::assertArrayNotHasKey(
            self::EXTRA_FIELD,
            CustomerManagerFields::pick($row),
            'an unregistered column stays out'
        );
        self::assertSame(
            'заметка магазина',
            CustomerManagerFields::pick($row, [self::EXTRA_FIELD])[self::EXTRA_FIELD],
            'a column the site registered through msExtraField must survive'
        );
    }

    public function testExtraFieldCannotPutBackACredentialColumn(): void
    {
        $picked = CustomerManagerFields::pick($this->customerRow(), ['password', 'token', self::EXTRA_FIELD]);

        foreach (self::CUSTOMER_SECRETS as $secret) {
            self::assertArrayNotHasKey($secret, $picked, 'an extra field must not re-open a credential column');
        }
        self::assertArrayHasKey(self::EXTRA_FIELD, $picked);
        self::assertNotContains('password', CustomerManagerFields::fieldKeys(['password']));
    }

    public function testExtraFieldKeysAreNormalized(): void
    {
        $keys = CustomerManagerFields::fieldKeys(['', 'id', self::EXTRA_FIELD, self::EXTRA_FIELD]);

        self::assertNotContains('', $keys, 'an empty key is ignored');
        self::assertSame(1, count(array_keys($keys, 'id', true)), 'a key already in the base set is not duplicated');
        self::assertSame(1, count(array_keys($keys, self::EXTRA_FIELD, true)), 'a repeated key is added once');
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

    public function testExcludedColumnsKeepsRegisteredExtraFieldInTheQuery(): void
    {
        $meta = ['id' => [], 'password' => [], self::EXTRA_FIELD => []];

        $excluded = CustomerManagerFields::excludedColumns($meta, [self::EXTRA_FIELD]);

        self::assertContains('password', $excluded);
        self::assertNotContains(
            self::EXTRA_FIELD,
            $excluded,
            'an extra field must still be read from the database'
        );
    }

    public function testComboProcessorHidesCredentialsWithoutCombo(): void
    {
        $row = $this->combobox()->prepareRow($this->object($this->customerRow()));

        foreach (self::CUSTOMER_SECRETS as $secret) {
            self::assertArrayNotHasKey($secret, $row);
        }
        self::assertSame('ivan@example.com', $row['email']);
        self::assertArrayNotHasKey(self::EXTRA_FIELD, $row, 'nothing is registered, so nothing is widened');
    }

    public function testComboProcessorReturnsRegisteredExtraField(): void
    {
        $row = $this->combobox([self::EXTRA_FIELD])->prepareRow($this->object($this->customerRow()));

        self::assertSame('заметка магазина', $row[self::EXTRA_FIELD]);
        foreach (self::CUSTOMER_SECRETS as $secret) {
            self::assertArrayNotHasKey($secret, $row);
        }
    }

    public function testComboProcessorStillReturnsIdAndFullname(): void
    {
        self::assertSame(
            ['id' => 7, 'fullname' => 'Иван Петров'],
            $this->combobox([self::EXTRA_FIELD], true)->prepareRow($this->object($this->customerRow()))
        );
    }

    public function testExtraFieldKeysAreResolvedOncePerRun(): void
    {
        $processor = $this->combobox([self::EXTRA_FIELD]);

        // $this->modx is absent here, so a second registry lookup would fail outright.
        $first = $processor->prepareRow($this->object($this->customerRow()));
        $second = $processor->prepareRow($this->object($this->customerRow()));

        self::assertArrayHasKey(self::EXTRA_FIELD, $first);
        self::assertSame($first, $second, 'extra-field keys are resolved once, not per row');
    }

    public function testCustomerGridRowHidesCredentialsAndKeepsActions(): void
    {
        $row = $this->grid()->prepareArray($this->customerRow());

        foreach (self::CUSTOMER_SECRETS as $secret) {
            self::assertArrayNotHasKey($secret, $row, 'grid page must not carry credentials');
        }
        self::assertSame('ivan@example.com', $row['email']);
        self::assertArrayHasKey('actions', $row, 'grid actions must survive the allowlist');
        self::assertCount(2, $row['actions']);
    }

    public function testCustomerGridRowReturnsRegisteredExtraField(): void
    {
        $row = $this->grid([self::EXTRA_FIELD])->prepareArray($this->customerRow());

        self::assertSame('заметка магазина', $row[self::EXTRA_FIELD]);
        self::assertArrayHasKey('actions', $row);
        foreach (self::CUSTOMER_SECRETS as $secret) {
            self::assertArrayNotHasKey($secret, $row);
        }
    }

    public function testUserRowHidesCredentialsWithoutCombo(): void
    {
        $row = $this->userProcessor()->prepareRow($this->object($this->userRow()));

        foreach (self::USER_SECRETS as $secret) {
            self::assertArrayNotHasKey($secret, $row, "{$secret} must never leave the processor");
        }
        self::assertSame('manager', $row['username']);
        self::assertSame(1, $row['active']);
        self::assertArrayNotHasKey('hash_class', $row, 'allowlist is fail-closed');
    }

    public function testUserComboBranchStillReturnsIdUsernameFullname(): void
    {
        self::assertSame(
            ['id' => 3, 'username' => 'manager', 'fullname' => 'Менеджер'],
            $this->userProcessor(true)->prepareRow($this->object($this->userRow()))
        );
    }

    public function testUserComboFallsBackToUsernameWhenFullnameEmpty(): void
    {
        $fields = $this->userRow();
        $fields['fullname'] = '';

        self::assertSame('manager', $this->userProcessor(true)->prepareRow($this->object($fields))['fullname']);
    }
}
