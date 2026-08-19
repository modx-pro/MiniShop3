<?php

declare(strict_types=1);

namespace MiniShop3\Services\Shipment;

final class ShipmentStatus
{
    public const PREPARING = 'preparing';
    public const SHIPPED = 'shipped';
    public const IN_TRANSIT = 'in_transit';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';
    public const RETURNED = 'returned';
    public const FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PREPARING,
            self::SHIPPED,
            self::IN_TRANSIT,
            self::DELIVERED,
            self::CANCELLED,
            self::RETURNED,
            self::FAILED,
        ];
    }
}
