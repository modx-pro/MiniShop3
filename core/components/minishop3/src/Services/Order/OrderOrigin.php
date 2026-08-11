<?php

namespace MiniShop3\Services\Order;

/**
 * Documented order creation origin for event context (#507).
 */
final class OrderOrigin
{
    public const MANAGER = 'manager';
    public const STOREFRONT = 'storefront';
    public const INTEGRATION = 'integration';

    /** Stored on msOrder.properties for integrations */
    public const PROPERTY_IDEMPOTENCY_KEY = 'idempotency_key';
    public const PROPERTY_ORIGIN = 'origin';

    /**
     * @param mixed $origin
     */
    public static function normalize($origin, string $default = self::MANAGER): string
    {
        $value = is_string($origin) ? strtolower(trim($origin)) : '';

        return match ($value) {
            self::MANAGER, self::STOREFRONT, self::INTEGRATION => $value,
            default => match ($default) {
                self::MANAGER, self::STOREFRONT, self::INTEGRATION => $default,
                default => self::MANAGER,
            },
        };
    }

    public static function isManager(string $origin): bool
    {
        return $origin === self::MANAGER;
    }
}
