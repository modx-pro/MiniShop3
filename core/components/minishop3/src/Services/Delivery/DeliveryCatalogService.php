<?php

declare(strict_types=1);

namespace MiniShop3\Services\Delivery;

use MiniShop3\Model\msDelivery;
use MiniShop3\Services\Catalog\CatalogLexicon;
use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Services\Catalog\CheckoutMemberMap;
use MODX\Revolution\modX;

/**
 * Public Web API catalog of active delivery methods (#568).
 *
 * Never exposes properties, class, or raw validation_rules.
 */
class DeliveryCatalogService
{
    /**
     * Storefront-safe fields (no integration secrets).
     *
     * @var list<string>
     */
    public const PUBLIC_FIELDS = [
        'id',
        'name',
        'description',
        'price',
        'weight_price',
        'distance_price',
        'logo',
        'position',
        'active',
        'free_delivery_amount',
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
        $includePayments = CatalogQuery::resolveBool($params, 'include_payments', true);
        $includeRequired = CatalogQuery::resolveBool($params, 'include_required_fields', false);
        $paymentIdsByDelivery = $includePayments
            ? (new CheckoutMemberMap($this->modx))->paymentIdsByDelivery()
            : [];

        $c = $this->modx->newQuery(msDelivery::class);
        $c->where(['active' => 1]);
        $c->sortby('position', 'ASC');
        $c->sortby('id', 'ASC');

        $items = [];
        foreach ($this->modx->getIterator(msDelivery::class, $c) as $delivery) {
            $items[] = $this->formatItem(
                $delivery,
                $includePayments,
                $includeRequired,
                $paymentIdsByDelivery
            );
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

        $delivery = $this->modx->getObject(msDelivery::class, [
            'id' => $id,
            'active' => 1,
        ]);
        if (!$delivery instanceof msDelivery) {
            return null;
        }

        $includePayments = CatalogQuery::resolveBool($params, 'include_payments', true);
        $paymentIdsByDelivery = $includePayments
            ? (new CheckoutMemberMap($this->modx))->paymentIdsByDelivery()
            : [];

        return $this->formatItem(
            $delivery,
            $includePayments,
            CatalogQuery::resolveBool($params, 'include_required_fields', false),
            $paymentIdsByDelivery
        );
    }

    /**
     * Project a row onto the public allowlist (no MODX required).
     *
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
                'weight_price', 'distance_price', 'free_delivery_amount' => self::normalizeNumber($raw),
                default => $raw,
            };
        }

        return $out;
    }

    /**
     * Field names that include a Rakit `required` rule (names only, no rule dump).
     *
     * @return list<string>
     */
    public static function extractRequiredFieldNames(mixed $validationRules): array
    {
        if (is_string($validationRules)) {
            if ($validationRules === '') {
                return [];
            }
            $decoded = json_decode($validationRules, true);
            $validationRules = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($validationRules)) {
            return [];
        }

        $names = [];
        foreach ($validationRules as $field => $rules) {
            if (!is_string($field) || $field === '' || !is_string($rules)) {
                continue;
            }
            $parts = array_map('trim', explode('|', $rules));
            if (in_array('required', $parts, true)) {
                $names[] = $field;
            }
        }

        return $names;
    }

    /**
     * @param array<int, list<int>> $paymentIdsByDelivery
     * @return array<string, mixed>
     */
    private function formatItem(
        msDelivery $delivery,
        bool $includePayments,
        bool $includeRequired,
        array $paymentIdsByDelivery
    ): array {
        $raw = [];
        foreach (self::PUBLIC_FIELDS as $field) {
            $raw[$field] = $delivery->get($field);
        }

        $item = self::projectPublicFields($raw);
        $item['name'] = CatalogLexicon::translateMs3Name($this->modx, (string) ($item['name'] ?? ''));

        if ($includePayments) {
            $deliveryId = (int) $delivery->get('id');
            $item['payment_ids'] = $paymentIdsByDelivery[$deliveryId] ?? [];
        }

        if ($includeRequired) {
            $item['required_fields'] = self::extractRequiredFieldNames($delivery->get('validation_rules'));
        }

        return $item;
    }

    private static function normalizeNumber(mixed $raw): int|float|string
    {
        if (is_int($raw) || is_float($raw)) {
            return $raw;
        }
        if (is_numeric($raw)) {
            return str_contains((string) $raw, '.') ? (float) $raw : (int) $raw;
        }

        return $raw ?? 0;
    }
}
