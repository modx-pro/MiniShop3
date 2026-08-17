<?php

declare(strict_types=1);

namespace MiniShop3\Services\Payment;

use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msPayment;
use MiniShop3\Services\Catalog\CatalogLexicon;
use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Services\Catalog\CheckoutMemberMap;
use MODX\Revolution\modX;

/**
 * Public Web API catalog of active payment methods (#569).
 *
 * Never exposes properties, class, or live payment links.
 */
class PaymentCatalogService
{
    /**
     * @var list<string>
     */
    public const PUBLIC_FIELDS = [
        'id',
        'name',
        'description',
        'price',
        'logo',
        'position',
        'active',
    ];

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function getList(array $params = []): array
    {
        $deliveryId = (int) ($params['delivery_id'] ?? 0);
        $includeDeliveryIds = CatalogQuery::resolveBool($params, 'include_delivery_ids', false);
        $members = new CheckoutMemberMap($this->modx);

        if ($deliveryId > 0 && !$members->isActiveDelivery($deliveryId)) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }

        $c = $this->modx->newQuery(msPayment::class);
        $c->where(['msPayment.active' => 1]);

        if ($deliveryId > 0) {
            $c->innerJoin(
                msDeliveryMember::class,
                'Member',
                'Member.payment_id = msPayment.id AND Member.delivery_id = ' . $deliveryId
            );
        }

        $c->sortby('msPayment.position', 'ASC');
        $c->sortby('msPayment.id', 'ASC');

        $deliveryIdsByPayment = $includeDeliveryIds ? $members->deliveryIdsByPayment() : [];

        $items = [];
        foreach ($this->modx->getIterator(msPayment::class, $c) as $payment) {
            $items[] = $this->formatItem($payment, $includeDeliveryIds, $deliveryIdsByPayment);
        }

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function getById(int $id, array $params = []): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $payment = $this->modx->getObject(msPayment::class, [
            'id' => $id,
            'active' => 1,
        ]);
        if (!$payment instanceof msPayment) {
            return null;
        }

        $includeDeliveryIds = CatalogQuery::resolveBool($params, 'include_delivery_ids', false);
        $deliveryIdsByPayment = $includeDeliveryIds
            ? (new CheckoutMemberMap($this->modx))->deliveryIdsByPayment()
            : [];

        return $this->formatItem($payment, $includeDeliveryIds, $deliveryIdsByPayment);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function projectPublicFields(array $data): array
    {
        $out = [];
        foreach (self::PUBLIC_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $raw = $data[$field];
            $out[$field] = match ($field) {
                'id', 'position' => (int) $raw,
                'active' => (bool) $raw,
                default => $raw,
            };
        }

        return $out;
    }

    /**
     * @param array<int, list<int>> $deliveryIdsByPayment
     * @return array<string, mixed>
     */
    private function formatItem(
        msPayment $payment,
        bool $includeDeliveryIds,
        array $deliveryIdsByPayment
    ): array {
        $raw = [];
        foreach (self::PUBLIC_FIELDS as $field) {
            $raw[$field] = $payment->get($field);
        }

        $item = self::projectPublicFields($raw);
        $item['name'] = CatalogLexicon::translateMs3Name($this->modx, (string) ($item['name'] ?? ''));

        if ($includeDeliveryIds) {
            $paymentId = (int) $payment->get('id');
            $item['delivery_ids'] = $deliveryIdsByPayment[$paymentId] ?? [];
        }

        return $item;
    }
}
