<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Services\Cart\CartItemManager;
use MiniShop3\Services\Inventory\AdjustableInventoryInterface;
use MiniShop3\Services\Inventory\InventoryException;
use MiniShop3\Services\Inventory\InventoryServiceInterface;
use MiniShop3\Services\Inventory\OrderInventoryCoordinator;
use MiniShop3\Utils\Utils;
use MODX\Revolution\modSystemEvent;
use MODX\Revolution\modX;

class ManagerOrderProductsService
{
    protected modX $modx;
    protected ?OrderLogService $orderLog;
    protected ?Utils $ms3Utils;
    protected ?OrderInventoryCoordinator $inventoryCoordinator;
    protected ?CartItemManager $cartItemManager;

    /** @var array<int, float> */
    private array $snapshotBeforeSync = [];

    /** @var list<array{product_id: int, available: float, required: float}> */
    private array $inventoryOverrides = [];

    public function __construct(
        modX $modx,
        ?OrderLogService $orderLog = null,
        ?Utils $ms3Utils = null,
        ?OrderInventoryCoordinator $inventoryCoordinator = null,
        ?CartItemManager $cartItemManager = null
    ) {
        $this->modx = $modx;
        $this->orderLog = $orderLog;
        $this->ms3Utils = $ms3Utils;
        $this->inventoryCoordinator = $inventoryCoordinator;
        $this->cartItemManager = $cartItemManager;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string, data: array<string, mixed>, status: int}
     */
    public function getProducts(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            return $this->error('Order ID is required', HttpStatus::BAD_REQUEST);
        }

        $c = $this->modx->newQuery(msOrderProduct::class);
        $c->where(['order_id' => $id]);
        $c->leftJoin(msProduct::class, 'Product', 'msOrderProduct.product_id = Product.id');
        $c->select($this->modx->getSelectColumns(msOrderProduct::class, 'msOrderProduct'));
        $c->select('Product.pagetitle');

        $products = [];
        $collection = $this->modx->getIterator(msOrderProduct::class, $c);
        foreach ($collection as $product) {
            $data = $product->toArray();
            $data['pagetitle'] = $product->get('pagetitle');
            $products[] = $data;
        }

        return $this->success(['results' => $products]);
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string, data: array<string, mixed>, status: int}
     */
    public function addProduct(array $params = []): array
    {
        $orderId = (int)($params['id'] ?? 0);
        $productId = (int)($params['product_id'] ?? 0);

        if (!$orderId) {
            return $this->error('Order ID is required', HttpStatus::BAD_REQUEST);
        }
        if (!$productId) {
            return $this->error('Product ID is required', HttpStatus::BAD_REQUEST);
        }

        // Get the order
        $order = $this->modx->getObject(msOrder::class, $orderId);
        if (!$order) {
            return $this->error('Order not found', HttpStatus::NOT_FOUND);
        }

        // Check order status (should not be final)
        $status = $this->modx->getObject(msOrderStatus::class, $order->get('status_id'));
        if ($status && $status->get('final')) {
            return $this->error('Cannot add products to finalized order', HttpStatus::BAD_REQUEST);
        }

        // Get the product
        $product = $this->modx->getObject(msProduct::class, $productId);
        if (!$product) {
            return $this->error('Product not found', HttpStatus::NOT_FOUND);
        }

        // Get product data
        $productData = $this->modx->getObject(msProductData::class, $productId);

        // Get values from params or product defaults
        $count = (int)($params['count'] ?? 1);
        if ($count < 1) {
            $count = 1;
        }

        $price = isset($params['price'])
            ? (float)$params['price']
            : ($productData ? (float)$productData->get('price') : 0);
        $weight = isset($params['weight'])
            ? (float)$params['weight']
            : ($productData ? (float)$productData->get('weight') : 0);
        $name = $params['name'] ?? $product->get('pagetitle');

        // Handle options
        $options = [];
        if (isset($params['options'])) {
            if (is_string($params['options'])) {
                $decoded = json_decode($params['options'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $options = $decoded;
                }
            } elseif (is_array($params['options'])) {
                $options = $params['options'];
            }
        }

        // Generate product_key (unique identifier for product variation) using CartItemManager
        $cartItemManager = $this->cartItemManager
            ?? $this->modx->services->get('ms3_cart_item_manager');
        $productKey = $cartItemManager->generateProductKey(['id' => $productId], $options);

        // Create order product record
        $orderProduct = $this->modx->newObject(msOrderProduct::class);
        $orderProduct->set('order_id', $orderId);
        $orderProduct->set('product_id', $productId);
        $orderProduct->set('product_key', $productKey);
        $orderProduct->set('name', $name);
        $orderProduct->set('count', $count);
        $orderProduct->set('price', $price);
        $orderProduct->set('weight', $weight);
        $orderProduct->set('cost', $count * $price);
        $orderProduct->set('options', !empty($options) ? json_encode($options) : null);

        $eventContext = [
            'mode' => modSystemEvent::MODE_NEW,
            // 'object' — MS2-style alias, 'msOrderProduct' — MS3-style; both point at the same row
            'object' => $orderProduct,
            'msOrderProduct' => $orderProduct,
            'msOrder' => $order,
        ];

        $response = $this->getMs3Utils()->invokeEvent('msOnBeforeCreateOrderProduct', $eventContext);
        if (!$response['success']) {
            return $this->error((string)$response['message'], HttpStatus::BAD_REQUEST);
        }

        $inventoryError = $this->syncBeforePersist($order, $orderProduct, true, $params);
        if ($inventoryError !== null) {
            return $inventoryError;
        }

        if (!$orderProduct->save()) {
            $this->compensateInventory($order, $this->snapshotBeforeSync);
            return $this->error('Failed to add product to order', HttpStatus::INTERNAL_SERVER_ERROR);
        }
        $this->logInventoryOverrides((int)$order->get('id'));

        // After-event: row already persisted, a plugin error cannot roll it back. Log and continue
        // so the client doesn't get a 4xx for a request that actually succeeded server-side.
        $response = $this->getMs3Utils()->invokeEvent('msOnCreateOrderProduct', $eventContext);
        if (!$response['success']) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                '[ms3] msOnCreateOrderProduct after-plugin reported error (persistence already done): '
                . $response['message']
            );
        }

        // Log product addition
        $this->getOrderLog()->addEntry(
            $orderId,
            msOrderLog::ACTION_PRODUCTS,
            [
                'operation' => 'add',
                'product_id' => $productId,
                'product_name' => $name,
                'count' => $count,
                'price' => $price,
                'cost' => $count * $price,
            ]
        );

        // Recalculate order totals
        $this->recalculateOrderTotals($order);

        // Return created product data
        $result = $orderProduct->toArray();
        $result['pagetitle'] = $product->get('pagetitle');

        return $this->success($result, 'Product added to order successfully');
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string, data: array<string, mixed>, status: int}
     */
    public function updateProduct(array $params = []): array
    {
        $orderId = (int)($params['id'] ?? 0);
        $productId = (int)($params['product_id'] ?? 0);

        if (!$orderId || !$productId) {
            return $this->error('Order ID and Product ID are required', HttpStatus::BAD_REQUEST);
        }

        // Find the order product record
        $orderProduct = $this->modx->getObject(msOrderProduct::class, [
            'id' => $productId,
            'order_id' => $orderId,
        ]);

        if (!$orderProduct) {
            return $this->error('Order product not found', HttpStatus::NOT_FOUND);
        }

        // Get the order to recalculate totals
        $order = $this->modx->getObject(msOrder::class, $orderId);
        if (!$order) {
            return $this->error('Order not found', HttpStatus::NOT_FOUND);
        }

        // Store old values for logging
        $oldValues = [
            'count' => $orderProduct->get('count'),
            'price' => $orderProduct->get('price'),
            'weight' => $orderProduct->get('weight'),
            'cost' => $orderProduct->get('cost'),
        ];

        // Allowed fields for update
        $allowedFields = ['count', 'price', 'weight', 'options'];
        $updated = false;
        $changes = [];

        // Per-field null semantics:
        //   - `options` (JSON): null = explicit clear from frontend (OrderView getOptionsForSave())
        //   - `count`/`price`/`weight` (numeric): null skipped — coercing null to 0/1 would
        //     silently destroy data when a partial payload accidentally carries `null`
        //     (PrimeVue InputNumber on clear, third-party API, batch scripts).
        //     Frontend must send explicit 0 / valid number to actually update these.
        $nullClearable = ['options'];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $params)) {
                continue;
            }
            $value = $params[$field];

            if ($value === null && !in_array($field, $nullClearable, true)) {
                continue;
            }

            $oldValue = $orderProduct->get($field);

            switch ($field) {
                case 'count':
                    $value = max(1, (int)$value);
                    break;
                case 'price':
                case 'weight':
                    $value = max(0, (float)$value);
                    break;
                case 'options':
                    // null passes through (clear), arrays get encoded as JSON
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    break;
            }

            // Track changes for logging
            if ($oldValue != $value) {
                $changes[$field] = ['old' => $oldValue, 'new' => $value];
            }

            $orderProduct->set($field, $value);
            $updated = true;
        }

        if (!$updated) {
            return $this->error('No fields to update', HttpStatus::BAD_REQUEST);
        }

        // Recalculate cost
        $count = (float)$orderProduct->get('count');
        $price = (float)$orderProduct->get('price');
        $newCost = $count * $price;
        $orderProduct->set('cost', $newCost);

        // Add cost change if it changed
        if ($oldValues['cost'] != $newCost) {
            $changes['cost'] = ['old' => $oldValues['cost'], 'new' => $newCost];
        }

        $eventContext = [
            'mode' => modSystemEvent::MODE_UPD,
            // 'object' — MS2-style alias, 'msOrderProduct' — MS3-style; both point at the same row
            'object' => $orderProduct,
            'msOrderProduct' => $orderProduct,
            'msOrder' => $order,
        ];

        $response = $this->getMs3Utils()->invokeEvent('msOnBeforeUpdateOrderProduct', $eventContext);
        if (!$response['success']) {
            return $this->error((string)$response['message'], HttpStatus::BAD_REQUEST);
        }

        $inventoryError = $this->syncBeforePersist($order, $orderProduct, true, $params);
        if ($inventoryError !== null) {
            return $inventoryError;
        }

        if (!$orderProduct->save()) {
            $this->compensateInventory($order, $this->snapshotBeforeSync);
            return $this->error('Failed to update order product', HttpStatus::INTERNAL_SERVER_ERROR);
        }
        $this->logInventoryOverrides((int)$order->get('id'));

        // After-event: row already persisted, a plugin error cannot roll it back. Log and continue
        // so the client doesn't get a 4xx for a request that actually succeeded server-side.
        $response = $this->getMs3Utils()->invokeEvent('msOnUpdateOrderProduct', $eventContext);
        if (!$response['success']) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                '[ms3] msOnUpdateOrderProduct after-plugin reported error (persistence already done): '
                . $response['message']
            );
        }

        // Log product update if there were changes
        if (!empty($changes)) {
            $this->getOrderLog()->addEntry(
                $orderId,
                msOrderLog::ACTION_PRODUCTS,
                [
                    'operation' => 'update',
                    'product_id' => $orderProduct->get('product_id'),
                    'product_name' => $orderProduct->get('name'),
                    'changes' => $changes,
                ]
            );
        }

        // Recalculate order totals
        $this->recalculateOrderTotals($order);

        return $this->success(
            $orderProduct->toArray(),
            'Order product updated successfully'
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string, data: array<string, mixed>, status: int}
     */
    public function deleteProduct(array $params = []): array
    {
        $orderId = (int)($params['id'] ?? 0);
        $productId = (int)($params['product_id'] ?? 0);

        if (!$orderId || !$productId) {
            return $this->error('Order ID and Product ID are required', HttpStatus::BAD_REQUEST);
        }

        // Find the order product record
        $orderProduct = $this->modx->getObject(msOrderProduct::class, [
            'id' => $productId,
            'order_id' => $orderId,
        ]);

        if (!$orderProduct) {
            return $this->error('Order product not found', HttpStatus::NOT_FOUND);
        }

        // Get the order to recalculate totals
        $order = $this->modx->getObject(msOrder::class, $orderId);
        if (!$order) {
            return $this->error('Order not found', HttpStatus::NOT_FOUND);
        }

        // Check if this is the last product
        $productCount = $this->modx->getCount(msOrderProduct::class, ['order_id' => $orderId]);
        if ($productCount <= 1) {
            return $this->error('Cannot delete the last product from order', HttpStatus::BAD_REQUEST);
        }

        // Store data for logging before removal
        $productData = [
            'product_id' => $orderProduct->get('product_id'),
            'product_name' => $orderProduct->get('name'),
            'count' => $orderProduct->get('count'),
            'price' => $orderProduct->get('price'),
            'cost' => $orderProduct->get('cost'),
        ];

        $eventContext = [
            'id' => $orderProduct->get('id'),
            // 'object' — MS2-style alias, 'msOrderProduct' — MS3-style; both point at the same row
            'object' => $orderProduct,
            'msOrderProduct' => $orderProduct,
            'msOrder' => $order,
        ];

        $response = $this->getMs3Utils()->invokeEvent('msOnBeforeRemoveOrderProduct', $eventContext);
        if (!$response['success']) {
            return $this->error((string)$response['message'], HttpStatus::BAD_REQUEST);
        }

        $inventoryError = $this->syncBeforePersist($order, $orderProduct, false, $params);
        if ($inventoryError !== null) {
            return $inventoryError;
        }

        if (!$orderProduct->remove()) {
            $this->compensateInventory($order, $this->snapshotBeforeSync);
            return $this->error('Failed to delete order product', HttpStatus::INTERNAL_SERVER_ERROR);
        }
        $this->logInventoryOverrides((int)$order->get('id'));

        // After-event: row already removed, a plugin error cannot roll it back. Log and continue
        // so the client doesn't get a 4xx for a request that actually succeeded server-side.
        $response = $this->getMs3Utils()->invokeEvent('msOnRemoveOrderProduct', $eventContext);
        if (!$response['success']) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                '[ms3] msOnRemoveOrderProduct after-plugin reported error (persistence already done): '
                . $response['message']
            );
        }

        // Log product removal
        $this->getOrderLog()->addEntry(
            $orderId,
            msOrderLog::ACTION_PRODUCTS,
            [
                'operation' => 'remove',
                'product_id' => $productData['product_id'],
                'product_name' => $productData['product_name'],
                'count' => $productData['count'],
                'price' => $productData['price'],
                'cost' => $productData['cost'],
            ]
        );

        // Recalculate order totals
        $this->recalculateOrderTotals($order);

        return $this->success(null, 'Order product deleted successfully');
    }

    public function recalculateOrderTotals(msOrder $order): void
    {
        $products = $this->modx->getIterator(msOrderProduct::class, [
            'order_id' => $order->get('id'),
        ]);
        $totals = OrderService::aggregateProductsTotals($products);
        $cartCost = $totals['cart_cost'];
        $weight = $totals['weight'];

        $order->set('cart_cost', $cartCost);
        $order->set('weight', $weight);
        // Recalculate total cost (cart + delivery; payment deltas are reflected in cost when persisted elsewhere)
        /** @var OrderService $orderService */
        $orderService = $this->modx->services->get('ms3_order_service');
        $deliveryCost = (float)$order->get('delivery_cost');
        $order->set('cost', $orderService->clampComputedTotal($order, $cartCost, $deliveryCost, 0.0));

        $order->save();
    }

    protected function getOrderLog(): OrderLogService
    {
        if ($this->orderLog === null) {
            if ($this->modx->services->has('ms3_order_log')) {
                $this->orderLog = $this->modx->services->get('ms3_order_log');
            } else {
                /** @var MiniShop3 $ms3 */
                $ms3 = $this->modx->services->get('ms3');
                $this->orderLog = new OrderLogService($this->modx, $ms3);
            }
        }

        return $this->orderLog;
    }

    protected function getMs3Utils(): Utils
    {
        if ($this->ms3Utils === null) {
            /** @var MiniShop3 $ms3 */
            $ms3 = $this->modx->services->get('ms3');
            $this->ms3Utils = $ms3->utils;
        }

        return $this->ms3Utils;
    }

    /**
     * Reconcile stock with the line that is about to be saved. Null means the
     * caller can persist. A returned error means the line must not be saved.
     *
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string, data: array<string, mixed>, status: int}|null
     */
    private function syncBeforePersist(msOrder $order, object $pending, bool $includePending, array $params): ?array
    {
        $this->snapshotBeforeSync = [];
        $this->inventoryOverrides = [];
        if (!OrderInventoryCoordinator::isInventoryEnabled($this->modx)) {
            return null;
        }
        $orderId = (int)$order->get('id');
        $this->snapshotBeforeSync = $this->heldSnapshot($orderId);
        $requestedForce = $this->requestedForce($params);
        $force = $requestedForce && $this->canOverrideInventory();

        try {
            $this->inventoryOverrides = $this->syncInventory(
                $order,
                $this->targetQty($orderId, $pending, $includePending),
                $force
            );
        } catch (InventoryException $exception) {
            return $this->inventoryFailure($exception, $requestedForce);
        }

        return null;
    }

    /**
     * @return array<int, float>
     */
    private function targetQty(int $orderId, object $pending, bool $includePending): array
    {
        $pendingId = (int)($pending->get('id') ?? 0);
        $sums = [];
        $lines = $this->modx->getIterator(msOrderProduct::class, ['order_id' => $orderId]);
        if (is_iterable($lines)) {
            foreach ($lines as $line) {
                if (!is_object($line) || !method_exists($line, 'get')) {
                    continue;
                }
                $lineId = (int)$line->get('id');
                if ($pendingId > 0 && $lineId === $pendingId) {
                    continue;
                }
                $this->addQty($sums, (int)$line->get('product_id'), (float)$line->get('count'));
            }
        }
        if ($includePending) {
            $this->addQty($sums, (int)$pending->get('product_id'), (float)$pending->get('count'));
        }

        return $sums;
    }

    /**
     * @param array<int, float> $sums
     */
    private function addQty(array &$sums, int $productId, float $qty): void
    {
        $qty = round($qty, 3);
        if ($productId <= 0 || $qty <= 0) {
            return;
        }
        $sums[$productId] = round(($sums[$productId] ?? 0.0) + $qty, 3);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function requestedForce(array $params): bool
    {
        $value = $params['force'] ?? false;
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool)$value;
    }

    private function canOverrideInventory(): bool
    {
        return (bool)$this->modx->hasPermission('msorder_inventory_override');
    }

    /**
     * @param array<int, float> $target
     * @return list<array{product_id: int, available: float, required: float}>
     * @throws InventoryException
     */
    private function syncInventory(msOrder $order, array $target, bool $force): array
    {
        if (!OrderInventoryCoordinator::isInventoryEnabled($this->modx)) {
            return [];
        }
        $coordinator = $this->inventoryCoordinator();

        return $coordinator ? $coordinator->reconcile($order, $target, $force) : [];
    }

    /**
     * @return array<int, float>
     */
    private function heldSnapshot(int $orderId): array
    {
        $inventory = $this->inventoryService();
        if (!$inventory instanceof AdjustableInventoryInterface) {
            return [];
        }
        $snapshot = [];
        foreach ($inventory->heldByProduct($orderId) as $productId => $row) {
            $snapshot[(int)$productId] = round((float)($row['qty'] ?? 0), 3);
        }

        return $snapshot;
    }

    /**
     * @param array<int, float> $snapshot
     */
    private function compensateInventory(msOrder $order, array $snapshot): void
    {
        if (!OrderInventoryCoordinator::isInventoryEnabled($this->modx)) {
            return;
        }
        try {
            $this->inventoryCoordinator()?->reconcile($order, $snapshot, true, false);
        } catch (\Throwable $exception) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[ms3] inventory compensate failed: ' . $exception->getMessage()
            );
        }
    }

    private function logInventoryOverrides(int $orderId): void
    {
        if ($this->inventoryOverrides === []) {
            return;
        }
        $userId = (int)$this->modx->user->id;
        foreach ($this->inventoryOverrides as $override) {
            $this->getOrderLog()->addEntry(
                $orderId,
                msOrderLog::ACTION_PRODUCTS,
                [
                    'operation' => 'inventory_override',
                    'user_id' => $userId,
                    'product_id' => $override['product_id'],
                    'available' => $override['available'],
                    'reserved' => $override['required'],
                ],
                false
            );
        }
    }

    /**
     * @return array{success: false, message: string, data: array<string, mixed>, status: int}
     */
    private function inventoryFailure(InventoryException $exception, bool $requestedForce): array
    {
        $placeholders = $exception->getPlaceholders();
        $shortage = $exception->getLexiconKey() === 'ms3_err_inventory_short'
            || $exception->getLexiconKey() === 'ms3_err_inventory_insufficient';
        $canOverride = $shortage && $this->canOverrideInventory();
        $data = [
            'product_id' => (int)($placeholders['product_id'] ?? $placeholders['id'] ?? 0),
            'available' => (float)($placeholders['available'] ?? 0),
            'required' => (float)($placeholders['required'] ?? $placeholders['qty'] ?? 0),
            'can_override' => $canOverride,
        ];
        $this->modx->lexicon->load('minishop3:default');
        $translated = $this->modx->lexicon($exception->getLexiconKey(), $placeholders);
        $message = is_string($translated) && $translated !== ''
            ? $translated
            : $exception->getMessage();
        if ($shortage && $requestedForce && !$this->canOverrideInventory()) {
            return $this->error($message, HttpStatus::FORBIDDEN, $data);
        }
        if ($shortage || $exception->getLexiconKey() === 'ms3_err_inventory_adjust_unsupported') {
            return $this->error($message, HttpStatus::CONFLICT, $data);
        }

        return $this->error($message, HttpStatus::BAD_REQUEST, $data);
    }

    private function inventoryCoordinator(): ?OrderInventoryCoordinator
    {
        if ($this->inventoryCoordinator instanceof OrderInventoryCoordinator) {
            return $this->inventoryCoordinator;
        }
        $inventory = $this->inventoryService();
        if (!$inventory instanceof InventoryServiceInterface) {
            return null;
        }
        $this->inventoryCoordinator = new OrderInventoryCoordinator($this->modx, $inventory);

        return $this->inventoryCoordinator;
    }

    private function inventoryService(): ?InventoryServiceInterface
    {
        if (!$this->modx->services->has('ms3_inventory')) {
            return null;
        }
        $inventory = $this->modx->services->get('ms3_inventory');

        return $inventory instanceof InventoryServiceInterface ? $inventory : null;
    }

    /**
     * @return array{success: true, message: string, data: mixed, status: int}
     */
    protected function success(mixed $data = [], string $message = ''): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'status' => HttpStatus::OK,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: false, message: string, data: array<string, mixed>, status: int}
     */
    protected function error(string $message, int $status, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
            'status' => $status,
        ];
    }
}
