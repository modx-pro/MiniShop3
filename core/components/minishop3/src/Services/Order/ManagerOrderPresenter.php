<?php

namespace MiniShop3\Services\Order;

use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msModelField;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Services\ExtraFields\RepeaterFieldService;
use MODX\Revolution\modX;
use xPDO\Om\xPDOObject;

class ManagerOrderPresenter
{
    /**
     * Internal order fields never exposed in Manager API responses.
     *
     * `token` is the secret used to authorize web order actions (see Web OrderController
     * `ms3_token`); the Vue manager never consumes it, so it must not leak in any order payload.
     */
    public const HIDDEN_ORDER_FIELDS = [
        'token',
    ];

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Merge address fields into order payload without overwriting order-level fields.
     *
     * msOrderAddress has its own `id`, `properties`, `createdon`, `updatedon` which
     * must not overwrite the corresponding msOrder fields in the API response.
     *
     * Note: extra fields for msOrderAddress are loaded separately in get() since
     * they require explicit column reads; create()/finalize() return transient
     * responses where the address is either empty or just created.
     */
    public function mergeAddressIntoOrderData(array $orderData, ?xPDOObject $address): array
    {
        if (!$address) {
            return $orderData;
        }

        $excludeFields = ['id', 'order_id', 'createdon', 'updatedon', 'properties'];
        foreach ($address->toArray() as $key => $value) {
            if (!in_array($key, $excludeFields, true)) {
                $orderData[$key] = $value;
            }
        }

        return $orderData;
    }

    /**
     * Снимок данных заказа для API карточки (как в get() после загрузки).
     */
    public function buildOrderPayloadFromModel(msOrder $order): array
    {
        $status = $order->getOne('Status');
        $delivery = $order->getOne('Delivery');
        $payment = $order->getOne('Payment');
        $address = $order->getOne('Address');

        $data = $order->toArray();
        $data['status_name'] = $status ? $status->get('name') : '';
        $data['color'] = $status ? $status->get('color') : '';
        $data['delivery_name'] = $delivery ? $delivery->get('name') : '';
        $data['payment_name'] = $payment ? $payment->get('name') : '';

        $orderExtraFields = $this->getExtraFieldKeys('MiniShop3\\Model\\msOrder');
        foreach ($orderExtraFields as $fieldKey) {
            $data[$fieldKey] = $order->get($fieldKey);
        }

        $data = $this->mergeAddressIntoOrderData($data, $address);

        if ($address) {
            $addressExtraFields = $this->getExtraFieldKeys('MiniShop3\\Model\\msOrderAddress');
            foreach ($addressExtraFields as $fieldKey) {
                $data[$fieldKey] = $address->get($fieldKey);
            }
        }

        return $this->formatOrder($data);
    }

    /**
     * Format order data for API response.
     */
    public function formatOrder(array $data): array
    {
        $ms3 = $this->modx->services->get('ms3');

        if (!empty($data['status_name']) && str_starts_with($data['status_name'], 'ms3_')) {
            $translated = $this->modx->lexicon($data['status_name']);
            if ($translated !== $data['status_name']) {
                $data['status_name'] = $translated;
            }
        }

        if (!empty($data['delivery_name']) && str_starts_with($data['delivery_name'], 'ms3_')) {
            $translated = $this->modx->lexicon($data['delivery_name']);
            if ($translated !== $data['delivery_name']) {
                $data['delivery_name'] = $translated;
            }
        }

        if (!empty($data['payment_name']) && str_starts_with($data['payment_name'], 'ms3_')) {
            $translated = $this->modx->lexicon($data['payment_name']);
            if ($translated !== $data['payment_name']) {
                $data['payment_name'] = $translated;
            }
        }

        $data['customer'] = trim(implode(' ', [
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
        ]));

        if ($ms3) {
            if (isset($data['cost'])) {
                $data['cost_formatted'] = $ms3->format->price($data['cost']);
            }
            if (isset($data['cart_cost'])) {
                $data['cart_cost_formatted'] = $ms3->format->price($data['cart_cost']);
            }
            if (isset($data['delivery_cost'])) {
                $data['delivery_cost_formatted'] = $ms3->format->price($data['delivery_cost']);
            }
            if (isset($data['weight'])) {
                $data['weight_formatted'] = $ms3->format->weight($data['weight']);
            }
        }

        foreach (self::HIDDEN_ORDER_FIELDS as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * @return array{ok: bool, value?: mixed, message?: string}
     */
    public function normalizeExtraFieldValue(string $modelClass, string $fieldKey, mixed $value): array
    {
        /** @var msExtraField|null $definition */
        $definition = $this->modx->getObject(msExtraField::class, [
            'class' => $modelClass,
            'key' => $fieldKey,
            'active' => true,
        ]);

        if (!$definition || $definition->get('xtype') !== RepeaterFieldService::XTYPE) {
            return ['ok' => true, 'value' => $value];
        }

        /** @var RepeaterFieldService $repeaterService */
        $repeaterService = $this->modx->services->get('ms3_repeater_field');
        $config = $repeaterService->parseConfig($definition->get('repeater_config'));

        try {
            return ['ok' => true, 'value' => $repeaterService->processValue($value, $config)];
        } catch (\InvalidArgumentException $e) {
            $this->modx->lexicon->load('minishop3:default');

            return [
                'ok' => false,
                'message' => $this->modx->lexicon('ms3_repeater_validation_error', [
                    'field' => $fieldKey,
                    'error' => $e->getMessage(),
                ]),
            ];
        }
    }

    /**
     * Get extra field keys for a specific model class.
     */
    public function getExtraFieldKeys(string $modelClass): array
    {
        $keys = [];

        $query = $this->modx->newQuery(msExtraField::class);
        $query->where([
            'class' => $modelClass,
            'active' => true,
        ]);

        foreach ($this->modx->getIterator(msExtraField::class, $query) as $field) {
            $keys[] = $field->get('key');
        }

        return $keys;
    }

    /**
     * Get field names from msModelField configuration.
     */
    public function getModelFieldNames(string $model): array
    {
        $names = [];

        $query = $this->modx->newQuery(msModelField::class);
        $query->where(['model' => $model]);

        foreach ($this->modx->getIterator(msModelField::class, $query) as $field) {
            $names[] = $field->get('name');
        }

        return $names;
    }

    /** Human-readable lexicon entry, or original key when missing/empty translation. */
    public function lexiconMessageOrKey(string $key): string
    {
        $text = $this->modx->lexicon($key);

        return ($text !== $key && $text !== '') ? $text : $key;
    }

    /**
     * Get status name by ID.
     */
    public function getStatusName(int $statusId): string
    {
        $status = $this->modx->getObject(msOrderStatus::class, $statusId);
        if (!$status) {
            return (string)$statusId;
        }

        $name = $status->get('name');
        if (str_starts_with($name, 'ms3_order_status_')) {
            $translated = $this->modx->lexicon($name);
            if ($translated !== $name) {
                return $translated;
            }
        }

        return $name;
    }
}
