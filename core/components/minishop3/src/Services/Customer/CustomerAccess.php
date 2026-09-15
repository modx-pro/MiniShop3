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
     * Whether is_blocked with blocked_until already in the past.
     *
     * Empty, null, or unparseable blocked_until is treated as a permanent block (not expired).
     */
    public static function isLockoutExpired(msCustomer $customer): bool
    {
        if (!$customer->get('is_blocked')) {
            return false;
        }

        $blockedUntil = $customer->get('blocked_until');
        if ($blockedUntil === null || $blockedUntil === '') {
            return false;
        }

        $timestamp = strtotime((string) $blockedUntil);
        if ($timestamp === false || $timestamp <= 0) {
            return false;
        }

        return $timestamp <= time();
    }
}
