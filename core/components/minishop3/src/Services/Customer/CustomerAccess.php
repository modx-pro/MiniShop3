<?php

declare(strict_types=1);

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msCustomer;

/**
 * Shared storefront/API access predicate for msCustomer rows.
 *
 * Permanent manager block: is_blocked with empty/unparseable blocked_until.
 * Temporary lockout: is_blocked with blocked_until in the future.
 * Expired lockout: is_blocked with blocked_until in the past — access allowed; callers may clear flags on login.
 */
final class CustomerAccess
{
    /**
     * Whether the customer must be denied storefront/API access.
     */
    public static function isAccessDenied(msCustomer $customer): bool
    {
        if (!$customer->get('is_active')) {
            return true;
        }

        return (bool) $customer->get('is_blocked') && !self::isLockoutExpired($customer);
    }

    /**
     * Whether password reset must be denied (ForgotPassword silent success path).
     *
     * Inactive accounts and permanent manager blocks (is_blocked without parseable
     * blocked_until) are denied. Timed lockouts — active or expired — may request reset.
     */
    public static function isPasswordResetDenied(msCustomer $customer): bool
    {
        if (!(bool) $customer->get('is_active')) {
            return true;
        }

        return (bool) $customer->get('is_blocked') && !self::hasTimedLockout($customer);
    }

    /**
     * Whether is_blocked with blocked_until already in the past.
     *
     * Empty, null, or unparseable blocked_until is treated as a permanent block (not expired).
     */
    public static function isLockoutExpired(msCustomer $customer): bool
    {
        $timestamp = self::blockedUntilTimestamp($customer);

        return $timestamp !== null && $timestamp <= time();
    }

    /**
     * Whether is_blocked with a real blocked_until (active or expired lockout, not a manager block).
     */
    public static function hasTimedLockout(msCustomer $customer): bool
    {
        return self::blockedUntilTimestamp($customer) !== null;
    }

    public static function liftTimedLockout(msCustomer $customer): void
    {
        if (self::blockedUntilTimestamp($customer) === null) {
            return;
        }

        $customer->set('is_blocked', false);
        $customer->set('blocked_until', null);
    }

    private static function blockedUntilTimestamp(msCustomer $customer): ?int
    {
        if (!$customer->get('is_blocked')) {
            return null;
        }

        $blockedUntil = $customer->get('blocked_until');
        if ($blockedUntil === null || $blockedUntil === '') {
            return null;
        }

        $timestamp = strtotime((string) $blockedUntil);
        if ($timestamp === false || $timestamp <= 0) {
            return null;
        }

        return $timestamp;
    }
}
