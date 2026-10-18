<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Processors;

use MiniShop3\Model\msCustomer;
use MiniShop3\Processors\Customer\GetListCombobox;
use MiniShop3\Processors\System\User\GetList as UserGetList;
use PHPUnit\Framework\TestCase;
use xPDO\Om\xPDOObject;

require_once dirname(__DIR__, 2) . '/stubs/ModxProcessorBasesStub.php';

/**
 * #908: combo processors must not leak credential columns when `combo` is absent.
 */
final class SecretFieldsTest extends TestCase
{
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
     * @param array<string, mixed> $properties
     */
    private function customerProcessor(array $properties): GetListCombobox
    {
        return new class ($properties) extends GetListCombobox {
            /** @param array<string, mixed> $properties */
            public function __construct(private array $properties)
            {
            }

            public function getProperty($key, $default = null)
            {
                return $this->properties[$key] ?? $default;
            }
        };
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function userProcessor(array $properties): UserGetList
    {
        return new class ($properties) extends UserGetList {
            /** @param array<string, mixed> $properties */
            public function __construct(private array $properties)
            {
            }

            public function getProperty($key, $default = null)
            {
                return $this->properties[$key] ?? $default;
            }
        };
    }

    /** @return array<string, mixed> */
    private function customerRow(): array
    {
        return [
            'id' => 7,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'email' => 'ivan@example.com',
            'phone' => '+70000000000',
            'token' => 'a1b2c3-session-token',
            'password' => '$2y$10$hashhashhashhashhash',
            'is_active' => true,
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
            'active' => 1,
        ];
    }

    public function testWithoutSecretsDropsPasswordAndToken(): void
    {
        $clean = msCustomer::withoutSecrets($this->customerRow());

        self::assertArrayNotHasKey('password', $clean);
        self::assertArrayNotHasKey('token', $clean);
        self::assertSame('ivan@example.com', $clean['email']);
        self::assertSame(7, $clean['id']);
    }

    public function testWithoutSecretsKeepsRowWhenNoSecretsPresent(): void
    {
        $row = ['id' => 1, 'email' => 'a@b.c'];

        self::assertSame($row, msCustomer::withoutSecrets($row));
    }

    public function testCustomerRowWithoutComboHidesCredentials(): void
    {
        $row = $this->customerProcessor([])->prepareRow($this->object($this->customerRow()));

        self::assertArrayNotHasKey('password', $row, 'password hash must never leave the processor');
        self::assertArrayNotHasKey('token', $row, 'session token must never leave the processor');
        self::assertSame('ivan@example.com', $row['email'], 'non-secret fields stay in the default branch');
        self::assertSame('Иван', $row['first_name']);
    }

    public function testCustomerComboBranchStillReturnsIdAndFullname(): void
    {
        $row = $this->customerProcessor(['combo' => 1])->prepareRow($this->object($this->customerRow()));

        self::assertSame(['id' => 7, 'fullname' => 'Иван Петров'], $row);
    }

    public function testUserRowWithoutComboHidesCredentials(): void
    {
        $row = $this->userProcessor([])->prepareRow($this->object($this->userRow()));

        foreach (['password', 'cachepwd', 'salt', 'remote_key', 'remote_data'] as $secret) {
            self::assertArrayNotHasKey($secret, $row, "{$secret} must never leave the processor");
        }
        self::assertSame('manager', $row['username'], 'non-secret fields stay in the default branch');
        self::assertSame(1, $row['active']);
    }

    public function testUserComboBranchStillReturnsIdUsernameFullname(): void
    {
        $row = $this->userProcessor(['combo' => 1])->prepareRow($this->object($this->userRow()));

        self::assertSame(['id' => 3, 'username' => 'manager', 'fullname' => 'Менеджер'], $row);
    }

    public function testUserComboFallsBackToUsernameWhenFullnameEmpty(): void
    {
        $fields = $this->userRow();
        $fields['fullname'] = '';

        $row = $this->userProcessor(['combo' => 1])->prepareRow($this->object($fields));

        self::assertSame('manager', $row['fullname']);
    }
}
