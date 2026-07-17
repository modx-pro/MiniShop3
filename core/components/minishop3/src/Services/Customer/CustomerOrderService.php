<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Services\Order\OrderStatusService;
use MODX\Revolution\modX;

/**
 * Customer order history for Web API (token-auth cabinet).
 *
 * Always scopes queries by customer_id. Public DTOs omit manager internals
 * (token, properties, user_id).
 */
class CustomerOrderService
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 100;

    /** Fallback when ms3_status_draft is unset */
    public const DEFAULT_DRAFT_STATUS_ID = 1;

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->modx->lexicon->load('minishop3:manager', 'minishop3:customer');
    }

    /**
     * Draft / checkout status id from system setting.
     */
    public function getDraftStatusId(): int
    {
        return (int)$this->modx->getOption('ms3_status_draft', null, self::DEFAULT_DRAFT_STATUS_ID)
            ?: self::DEFAULT_DRAFT_STATUS_ID;
    }

    /**
     * Normalize list query params (limit, offset, status).
     *
     * @param array $query Typically $_GET
     * @param int $draftStatusId Status id to never use as filter (default 1)
     * @return array{limit: int, offset: int, status_id: int|null}
     */
    public static function normalizeListParams(
        array $query,
        int $draftStatusId = self::DEFAULT_DRAFT_STATUS_ID
    ): array {
        $limit = (int)($query['limit'] ?? self::DEFAULT_LIMIT);
        $limit = $limit < 1 ? self::DEFAULT_LIMIT : min($limit, self::MAX_LIMIT);

        $statusId = isset($query['status']) ? (int)$query['status'] : null;
        if ($statusId === null || $statusId <= 0 || $statusId === $draftStatusId) {
            $statusId = null;
        }

        return [
            'limit' => $limit,
            'offset' => max(0, (int)($query['offset'] ?? 0)),
            'status_id' => $statusId,
        ];
    }

    /**
     * Build public order summary fields from a raw order row.
     *
     * @param array $orderRow Order field map (e.g. toArray())
     * @param array{name?: string, color?: string} $statusMeta
     */
    public static function buildPublicOrderSummary(array $orderRow, array $statusMeta, bool $canCancel): array
    {
        return [
            'id' => (int)($orderRow['id'] ?? 0),
            'uuid' => (string)($orderRow['uuid'] ?? ''),
            'num' => (string)($orderRow['num'] ?? ''),
            'createdon' => $orderRow['createdon'] ?? null,
            'updatedon' => $orderRow['updatedon'] ?? null,
            'cost' => (float)($orderRow['cost'] ?? 0),
            'cart_cost' => (float)($orderRow['cart_cost'] ?? 0),
            'delivery_cost' => (float)($orderRow['delivery_cost'] ?? 0),
            'weight' => (float)($orderRow['weight'] ?? 0),
            'status_id' => (int)($orderRow['status_id'] ?? 0),
            'status_name' => (string)($statusMeta['name'] ?? ''),
            'status_color' => (string)($statusMeta['color'] ?? ''),
            'delivery_id' => (int)($orderRow['delivery_id'] ?? 0),
            'payment_id' => (int)($orderRow['payment_id'] ?? 0),
            'context' => (string)($orderRow['context'] ?? ''),
            'order_comment' => (string)($orderRow['order_comment'] ?? ''),
            'can_cancel' => $canCancel,
        ];
    }

    /**
     * List submitted orders for a customer.
     *
     * @return array{orders: array, total: int, limit: int, offset: int}
     */
    public function listForCustomer(int $customerId, int $limit, int $offset, ?int $statusId = null): array
    {
        $where = $this->buildCustomerOrdersWhere($customerId, $statusId);
        $total = $this->modx->getCount(msOrder::class, $where);

        $statusMap = $this->loadStatusMap();
        /** @var OrderStatusService $orderStatusService */
        $orderStatusService = $this->modx->services->get('ms3_order_status');
        $allowedCancelIds = $orderStatusService->getAllowedCancelStatusIds();

        $query = $this->modx->newQuery(msOrder::class);
        $query->where($where);
        $query->sortby('msOrder.createdon', 'DESC');
        $query->limit($limit, $offset);

        $orders = [];
        /** @var msOrder $order */
        foreach ($this->modx->getCollection(msOrder::class, $query) as $order) {
            $statusIdRow = (int)$order->get('status_id');
            $orders[] = self::buildPublicOrderSummary(
                $order->toArray(),
                $statusMap[$statusIdRow] ?? ['name' => '', 'color' => ''],
                in_array($statusIdRow, $allowedCancelIds, true)
            );
        }

        return [
            'orders' => $orders,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Get one order owned by the customer, with products and related entities.
     *
     * @return array|null Detail DTO or null if not found / not owned / draft
     */
    public function getForCustomer(int $customerId, int $orderId): ?array
    {
        $order = $this->findCustomerOrder($customerId, $orderId);
        if (!$order) {
            return null;
        }

        /** @var OrderStatusService $orderStatusService */
        $orderStatusService = $this->modx->services->get('ms3_order_status');
        $statusId = (int)$order->get('status_id');
        $status = $order->getOne('Status');
        $statusMeta = [
            'name' => $status instanceof msOrderStatus
                ? $this->translateStatusName((string)$status->get('name'))
                : '',
            'color' => $status instanceof msOrderStatus ? (string)$status->get('color') : '',
        ];

        $address = $order->getOne('Address');

        return [
            'order' => self::buildPublicOrderSummary(
                $order->toArray(),
                $statusMeta,
                in_array($statusId, $orderStatusService->getAllowedCancelStatusIds(), true)
            ),
            'products' => $this->formatOrderProducts((int)$order->get('id')),
            'delivery' => $this->formatDeliveryOrPayment($order->getOne('Delivery') ?: null),
            'payment' => $this->formatDeliveryOrPayment($order->getOne('Payment') ?: null),
            'address' => $address instanceof msOrderAddress ? $this->formatAddress($address) : null,
        ];
    }

    /**
     * Cancel an order owned by the customer (non-draft, allowed status).
     *
     * @return array{ok: true, order_id: int, status_id: int}|array{ok: false, error: string, message?: string}
     */
    public function cancelForCustomer(int $customerId, int $orderId): array
    {
        $order = $this->findCustomerOrder($customerId, $orderId);
        if (!$order) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        /** @var OrderStatusService $orderStatusService */
        $orderStatusService = $this->modx->services->get('ms3_order_status');
        $currentStatusId = (int)$order->get('status_id');

        if (!in_array($currentStatusId, $orderStatusService->getAllowedCancelStatusIds(), true)) {
            return ['ok' => false, 'error' => 'status'];
        }

        $cancelledStatusId = (int)$this->modx->getOption('ms3_status_canceled', null, 5);
        $result = $orderStatusService->change($orderId, $cancelledStatusId);

        if ($result !== true) {
            return [
                'ok' => false,
                'error' => 'failed',
                'message' => is_string($result) ? $result : null,
            ];
        }

        return [
            'ok' => true,
            'order_id' => $orderId,
            'status_id' => $cancelledStatusId,
        ];
    }

    /**
     * Find non-draft order owned by customer.
     */
    protected function findCustomerOrder(int $customerId, int $orderId): ?msOrder
    {
        if ($orderId < 1 || $customerId < 1) {
            return null;
        }

        /** @var msOrder|null $order */
        $order = $this->modx->getObject(msOrder::class, [
            'id' => $orderId,
            'customer_id' => $customerId,
            'status_id:!=' => $this->getDraftStatusId(),
        ]);

        return $order ?: null;
    }

    protected function buildCustomerOrdersWhere(int $customerId, ?int $statusId): array
    {
        $draftStatusId = $this->getDraftStatusId();
        $where = [
            'customer_id' => $customerId,
            'status_id:!=' => $draftStatusId,
        ];

        if ($statusId !== null && $statusId !== $draftStatusId) {
            $where['status_id'] = $statusId;
            unset($where['status_id:!=']);
        }

        return $where;
    }

    /**
     * @return array<int, array{name: string, color: string}>
     */
    protected function loadStatusMap(): array
    {
        $query = $this->modx->newQuery(msOrderStatus::class);
        $query->sortby('position', 'ASC');

        $map = [];
        /** @var msOrderStatus $status */
        foreach ($this->modx->getIterator(msOrderStatus::class, $query) as $status) {
            $map[(int)$status->get('id')] = [
                'name' => $this->translateStatusName((string)$status->get('name')),
                'color' => (string)$status->get('color'),
            ];
        }

        return $map;
    }

    protected function translateStatusName(string $name): string
    {
        if (!str_starts_with($name, 'ms3_order_status_')) {
            return $name;
        }

        $translated = $this->modx->lexicon($name);

        return $translated !== $name ? $translated : $name;
    }

    /**
     * @return array<int, array>
     */
    protected function formatOrderProducts(int $orderId): array
    {
        $query = $this->modx->newQuery(msOrderProduct::class);
        $query->where(['order_id' => $orderId]);
        $query->sortby('msOrderProduct.id', 'ASC');

        $products = [];
        /** @var msOrderProduct $product */
        foreach ($this->modx->getCollection(msOrderProduct::class, $query) as $product) {
            $options = $product->get('options');
            $products[] = [
                'id' => (int)$product->get('id'),
                'product_id' => (int)$product->get('product_id'),
                'name' => (string)$product->get('name'),
                'count' => (int)$product->get('count'),
                'price' => (float)$product->get('price'),
                'weight' => (float)$product->get('weight'),
                'cost' => (float)$product->get('cost'),
                'options' => is_array($options) ? $options : [],
            ];
        }

        return $products;
    }

    protected function formatDeliveryOrPayment(?object $entity): ?array
    {
        if (!$entity) {
            return null;
        }

        return [
            'id' => (int)$entity->get('id'),
            'name' => (string)$entity->get('name'),
            'description' => (string)$entity->get('description'),
            'price' => $entity->get('price'),
            'logo' => (string)$entity->get('logo'),
        ];
    }

    protected function formatAddress(msOrderAddress $address): array
    {
        return [
            'first_name' => (string)$address->get('first_name'),
            'last_name' => (string)$address->get('last_name'),
            'phone' => (string)$address->get('phone'),
            'email' => (string)$address->get('email'),
            'country' => (string)$address->get('country'),
            'index' => (string)$address->get('index'),
            'region' => (string)$address->get('region'),
            'city' => (string)$address->get('city'),
            'metro' => (string)$address->get('metro'),
            'street' => (string)$address->get('street'),
            'building' => (string)$address->get('building'),
            'entrance' => (string)$address->get('entrance'),
            'floor' => (string)$address->get('floor'),
            'room' => (string)$address->get('room'),
            'comment' => (string)$address->get('comment'),
            'text_address' => (string)$address->get('text_address'),
        ];
    }
}
