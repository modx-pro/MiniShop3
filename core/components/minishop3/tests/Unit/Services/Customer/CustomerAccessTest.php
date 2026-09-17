<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\CustomerAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomerAccessTest extends TestCase
{
    #[DataProvider('accessDeniedCases')]
    public function testIsAccessDenied(array $fields, bool $expectedDenied): void
    {
        self::assertSame($expectedDenied, CustomerAccess::isAccessDenied($this->customer($fields)));
    }

    #[DataProvider('lockoutExpiredCases')]
    public function testIsLockoutExpired(array $fields, bool $expectedExpired): void
    {
        self::assertSame($expectedExpired, CustomerAccess::isLockoutExpired($this->customer($fields)));
    }

    #[DataProvider('timedLockoutCases')]
    public function testHasTimedLockout(array $fields, bool $expected): void
    {
        self::assertSame($expected, CustomerAccess::hasTimedLockout($this->customer($fields)));
    }

    #[DataProvider('passwordResetDeniedCases')]
    public function testIsPasswordResetDenied(array $fields, bool $expectedDenied): void
    {
        self::assertSame($expectedDenied, CustomerAccess::isPasswordResetDenied($this->customer($fields)));
    }

    public function testLiftTimedLockoutClearsOnlyParseableUntil(): void
    {
        $lockout = $this->customer([
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() + 60),
        ]);
        CustomerAccess::liftTimedLockout($lockout);
        self::assertFalse((bool) $lockout->get('is_blocked'));
        self::assertNull($lockout->get('blocked_until'));

        $permanent = $this->customer([
            'is_blocked' => 1,
            'blocked_until' => null,
        ]);
        CustomerAccess::liftTimedLockout($permanent);
        self::assertTrue((bool) $permanent->get('is_blocked'));
        self::assertNull($permanent->get('blocked_until'));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function accessDeniedCases(): iterable
    {
        yield 'active not blocked' => [['is_active' => 1, 'is_blocked' => 0], false];
        yield 'inactive' => [['is_active' => 0, 'is_blocked' => 0], true];
        yield 'permanent manager block' => [['is_active' => 1, 'is_blocked' => 1, 'blocked_until' => null], true];
        yield 'permanent empty until' => [['is_active' => 1, 'is_blocked' => 1, 'blocked_until' => ''], true];
        yield 'unparseable until' => [['is_active' => 1, 'is_blocked' => 1, 'blocked_until' => 'not-a-date'], true];
        yield 'mysql zero datetime is permanent' => [['is_active' => 1, 'is_blocked' => 1, 'blocked_until' => '0000-00-00 00:00:00'], true];
        yield 'future lockout' => [[
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() + 3600),
        ], true];
        yield 'expired lockout allowed' => [[
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() - 60),
        ], false];
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function lockoutExpiredCases(): iterable
    {
        yield 'not blocked' => [['is_blocked' => 0], false];
        yield 'permanent block' => [['is_blocked' => 1, 'blocked_until' => null], false];
        yield 'mysql zero datetime is not expired' => [['is_blocked' => 1, 'blocked_until' => '0000-00-00 00:00:00'], false];
        yield 'future lockout' => [[
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() + 3600),
        ], false];
        yield 'expired lockout' => [[
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() - 60),
        ], true];
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function passwordResetDeniedCases(): iterable
    {
        yield 'active not blocked' => [['is_active' => 1, 'is_blocked' => 0], false];
        yield 'inactive' => [['is_active' => 0, 'is_blocked' => 0], true];
        yield 'inactive with timed lockout still denied' => [[
            'is_active' => 0,
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() + 3600),
        ], true];
        yield 'permanent manager block' => [['is_active' => 1, 'is_blocked' => 1, 'blocked_until' => null], true];
        yield 'permanent empty until' => [['is_active' => 1, 'is_blocked' => 1, 'blocked_until' => ''], true];
        yield 'unparseable until' => [['is_active' => 1, 'is_blocked' => 1, 'blocked_until' => 'not-a-date'], true];
        yield 'mysql zero datetime is permanent' => [['is_active' => 1, 'is_blocked' => 1, 'blocked_until' => '0000-00-00 00:00:00'], true];
        yield 'future timed lockout allowed' => [[
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() + 3600),
        ], false];
        yield 'expired timed lockout allowed' => [[
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() - 60),
        ], false];
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function timedLockoutCases(): iterable
    {
        yield 'not blocked' => [['is_blocked' => 0, 'blocked_until' => date('Y-m-d H:i:s', time() + 60)], false];
        yield 'permanent block' => [['is_blocked' => 1, 'blocked_until' => null], false];
        yield 'mysql zero datetime' => [['is_blocked' => 1, 'blocked_until' => '0000-00-00 00:00:00'], false];
        yield 'future lockout' => [[
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() + 3600),
        ], true];
        yield 'expired lockout' => [[
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() - 60),
        ], true];
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function customer(array $fields): msCustomer
    {
        return new FakeAccessCustomer($fields);
    }
}

final class FakeAccessCustomer extends msCustomer
{
    /** @param array<string, mixed> $fields */
    public function __construct(private array $fields)
    {
    }

    public function get($key)
    {
        return $this->fields[$key] ?? null;
    }

    public function set($key, $value, $vType = '')
    {
        $this->fields[$key] = $value;

        return $this;
    }
}
