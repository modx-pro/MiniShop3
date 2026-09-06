<?php

declare(strict_types=1);

namespace MiniShop3\Services\Cart;

use MiniShop3\Model\msProductData;
use MODX\Revolution\modX;

/**
 * Web API projection for cart/get and cart mutations (#570).
 *
 * Does not change draft storage. Cart totals stay in CartItemManager::calculateStatus.
 * Checkout delivery/payment/final live on GET /api/v1/order/cost, not here.
 */
class CartResponseNormalizer
{
    private const MONEY_SCALE = 2;
    private const WEIGHT_SCALE = 3;

    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * @param array<string, mixed> $data Domain cart payload (cart + status)
     * @return array<string, mixed>
     */
    public function normalize(array $data, bool $includeThumbs = false): array
    {
        $cartMap = self::legacyCartMap($data['cart'] ?? []);
        $items = self::projectItems($cartMap);
        if ($includeThumbs) {
            $items = $this->withThumbs($items);
        }

        $data['cart'] = $cartMap === [] ? new \stdClass() : $cartMap;
        $data['items'] = $items;
        $data['status'] = self::projectStatus($data['status'] ?? []);

        return $data;
    }

    /**
     * @param mixed $cart
     * @return array<string, array<string, mixed>>
     */
    private static function legacyCartMap(mixed $cart): array
    {
        if (!is_array($cart) || $cart === []) {
            return [];
        }

        $out = [];
        if (array_is_list($cart)) {
            foreach ($cart as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $key = (string) ($row['product_key'] ?? '');
                if ($key !== '') {
                    $out[$key] = $row;
                }
            }

            return $out;
        }

        foreach ($cart as $key => $row) {
            if (is_array($row)) {
                $out[(string) $key] = $row;
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $cartMap
     * @return list<array<string, mixed>>
     */
    private static function projectItems(array $cartMap): array
    {
        $keys = array_keys($cartMap);
        usort(
            $keys,
            static function (string|int $a, string|int $b) use ($cartMap): int {
                $idA = (int) ($cartMap[$a]['id'] ?? 0);
                $idB = (int) ($cartMap[$b]['id'] ?? 0);

                return $idA <=> $idB ?: strcmp((string) $a, (string) $b);
            }
        );

        $items = [];
        foreach ($keys as $key) {
            $items[] = self::projectItem((string) $key, $cartMap[$key]);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function projectItem(string $productKey, array $raw): array
    {
        $props = self::assoc($raw['properties'] ?? []);
        $options = self::assoc($raw['options'] ?? []);
        $count = (int) ($raw['count'] ?? 0);
        $discountPrice = $props['discount_price'] ?? 0;

        return [
            'product_key' => $productKey !== '' ? $productKey : (string) ($raw['product_key'] ?? ''),
            'product_id' => (int) ($raw['product_id'] ?? 0),
            'name' => (string) ($raw['name'] ?? ''),
            'count' => $count,
            'price' => self::money($raw['price'] ?? 0),
            'cost' => self::money($raw['cost'] ?? 0),
            'weight' => self::weight($raw['weight'] ?? 0),
            'options' => $options === [] ? new \stdClass() : $options,
            'old_price' => self::money($props['old_price'] ?? 0),
            'discount_price' => self::money($discountPrice),
            'discount_cost' => self::money($props['discount_cost'] ?? $discountPrice * $count),
        ];
    }

    /**
     * @param array<string, mixed> $status
     * @return array{
     *     total_positions: int,
     *     total_count: int,
     *     total_cost: float,
     *     total_weight: float,
     *     total_discount: float
     * }
     */
    private static function projectStatus(array $status): array
    {
        return [
            'total_positions' => (int) ($status['total_positions'] ?? 0),
            'total_count' => (int) ($status['total_count'] ?? 0),
            'total_cost' => self::money($status['total_cost'] ?? 0),
            'total_weight' => self::weight($status['total_weight'] ?? 0),
            'total_discount' => self::money($status['total_discount'] ?? 0),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function withThumbs(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = (int) ($item['product_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $urls = $this->lookupThumbs(array_values($ids));
        foreach ($items as $i => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $url = trim($urls[$productId] ?? '');
            $items[$i]['thumb'] = $url !== '' ? $url : null;
        }

        return $items;
    }

    /**
     * @param list<int> $productIds
     * @return array<int, string>
     */
    protected function lookupThumbs(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $c = $this->modx->newQuery(msProductData::class);
        $c->where(['id:IN' => $productIds]);
        $c->select('id, thumb');

        $out = [];
        /** @var msProductData $row */
        foreach ($this->modx->getCollection(msProductData::class, $c) ?: [] as $row) {
            $id = (int) $row->get('id');
            $url = trim((string) $row->get('thumb'));
            if ($id > 0 && $url !== '') {
                $out[$id] = $url;
            }
        }

        return $out;
    }

    private static function money(mixed $value): float
    {
        return round((float) $value, self::MONEY_SCALE);
    }

    private static function weight(mixed $value): float
    {
        return round((float) $value, self::WEIGHT_SCALE);
    }

    /**
     * @return array<string, mixed>
     */
    private static function assoc(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }
}
