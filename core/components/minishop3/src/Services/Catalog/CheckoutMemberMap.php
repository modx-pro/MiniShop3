<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Batched active delivery↔payment member maps for checkout discovery (#568/#569).
 */
final class CheckoutMemberMap
{
    private modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    public function isActiveDelivery(int $deliveryId): bool
    {
        if ($deliveryId <= 0) {
            return false;
        }

        return (bool) $this->modx->getCount(msDelivery::class, [
            'id' => $deliveryId,
            'active' => 1,
        ]);
    }

    /**
     * @return array<int, list<int>> delivery_id => payment ids (position ASC, id ASC)
     */
    public function paymentIdsByDelivery(): array
    {
        $c = $this->modx->newQuery(msPayment::class);
        $c->setClassAlias('msPayment');
        $c->select('msPayment.id AS payment_id, Member.delivery_id AS delivery_id');
        $c->innerJoin(
            msDeliveryMember::class,
            'Member',
            'Member.payment_id = msPayment.id'
        );
        $c->innerJoin(
            msDelivery::class,
            'Delivery',
            'Delivery.id = Member.delivery_id AND Delivery.active = 1'
        );
        $c->where(['msPayment.active' => 1]);
        $c->sortby('msPayment.position', 'ASC');
        $c->sortby('msPayment.id', 'ASC');

        return $this->fetchGroupedIds($c, 'delivery_id', 'payment_id');
    }

    /**
     * @return array<int, list<int>> payment_id => delivery ids (position ASC, id ASC)
     */
    public function deliveryIdsByPayment(): array
    {
        $c = $this->modx->newQuery(msDelivery::class);
        $c->setClassAlias('msDelivery');
        $c->select('msDelivery.id AS delivery_id, Member.payment_id AS payment_id');
        $c->innerJoin(
            msDeliveryMember::class,
            'Member',
            'Member.delivery_id = msDelivery.id'
        );
        $c->innerJoin(
            msPayment::class,
            'Payment',
            'Payment.id = Member.payment_id AND Payment.active = 1'
        );
        $c->where(['msDelivery.active' => 1]);
        $c->sortby('msDelivery.position', 'ASC');
        $c->sortby('msDelivery.id', 'ASC');

        return $this->fetchGroupedIds($c, 'payment_id', 'delivery_id');
    }

    /**
     * @return array<int, list<int>>
     */
    private function fetchGroupedIds(xPDOQuery $query, string $groupKey, string $idKey): array
    {
        if (!$query->prepare() || !$query->stmt->execute()) {
            return [];
        }

        $map = [];
        while ($row = $query->stmt->fetch(\PDO::FETCH_ASSOC)) {
            $groupId = (int) ($row[$groupKey] ?? 0);
            $memberId = (int) ($row[$idKey] ?? 0);
            if ($groupId <= 0 || $memberId <= 0) {
                continue;
            }
            $map[$groupId][] = $memberId;
        }

        return $map;
    }
}
