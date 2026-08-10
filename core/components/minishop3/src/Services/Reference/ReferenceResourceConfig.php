<?php

namespace MiniShop3\Services\Reference;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msPayment;
use MiniShop3\Services\Grid\ManagerListFilterPolicy;

/**
 * Resource parameters for {@see Ms3ReferenceCrudService} (deliveries / payments).
 *
 * Not a generic REST-for-any-table framework — only the two mirrored mgr references.
 */
final class ReferenceResourceConfig
{
    /**
     * @param class-string $modelClass
     * @param list<string> $allowedFields
     * @param array<string, string> $filterMap
     * @param list<string> $floatFields
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $labelSingular,
        public readonly string $labelPlural,
        public readonly array $allowedFields,
        public readonly array $filterMap,
        public readonly string $memberOwnFk,
        public readonly string $memberPeerFk,
        public readonly string $peerLabelSingular,
        public readonly array $floatFields = [],
        /** When set: embed peer ids on get and replace peer links on create/update. */
        public readonly ?string $embedLinksKey = null,
    ) {
    }

    public static function forDeliveries(): self
    {
        return new self(
            modelClass: msDelivery::class,
            labelSingular: 'Delivery',
            labelPlural: 'Deliveries',
            allowedFields: [
                'name',
                'description',
                'price',
                'weight_price',
                'distance_price',
                'logo',
                'position',
                'active',
                'class',
                'properties',
                'validation_rules',
                'free_delivery_amount',
            ],
            filterMap: ManagerListFilterPolicy::DELIVERY_FILTER_MAP,
            memberOwnFk: 'delivery_id',
            memberPeerFk: 'payment_id',
            peerLabelSingular: 'Payment',
            floatFields: ['weight_price', 'distance_price', 'free_delivery_amount'],
            embedLinksKey: 'payments',
        );
    }

    public static function forPayments(): self
    {
        return new self(
            modelClass: msPayment::class,
            labelSingular: 'Payment',
            labelPlural: 'Payments',
            allowedFields: [
                'name',
                'description',
                'price',
                'logo',
                'position',
                'active',
                'class',
                'properties',
            ],
            filterMap: ManagerListFilterPolicy::PAYMENT_FILTER_MAP,
            memberOwnFk: 'payment_id',
            memberPeerFk: 'delivery_id',
            peerLabelSingular: 'Delivery',
            floatFields: [],
            embedLinksKey: null,
        );
    }
}
