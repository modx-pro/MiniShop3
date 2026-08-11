<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MODX\Revolution\modSystemEvent;
use MODX\Revolution\modX;
use Ramsey\Uuid\Uuid;

/**
 * Order Draft Manager
 *
 * Manages order draft lifecycle: creation, retrieval, and updates.
 * Drafts are orders with status = ms3_status_draft (typically 1).
 */
class OrderDraftManager
{
    protected modX $modx;
    protected MiniShop3 $ms3;

    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
    }

    /**
     * Get existing draft order by token
     *
     * Hybrid search strategy:
     * 1. First try to find by token (works for guests and API)
     * 2. If not found and customer_id exists in session - search by customer_id
     * 3. If found by customer_id - sync token to avoid future mismatches
     */
    public function getDraft(string $token, string $ctx = 'web'): ?msOrder
    {
        if (empty($token)) {
            return null;
        }

        $status_draft = (int) $this->modx->getOption('ms3_status_draft', null, 1) ?: 1;

        // 1. Try to find by token first (primary method)
        $draft = $this->modx->getObject(msOrder::class, [
            'token' => $token,
            'status_id' => $status_draft,
            'context' => $ctx,
        ]);

        if ($draft) {
            return $draft;
        }

        // 2. Fallback: search by customer_id for authenticated customers
        //    Sort by id DESC to get the most recent draft when multiple exist
        $customerId = (int)($_SESSION['ms3']['customer_id'] ?? 0);
        if ($customerId > 0) {
            $q = $this->modx->newQuery(msOrder::class);
            $q->where([
                'customer_id' => $customerId,
                'status_id' => $status_draft,
                'context' => $ctx,
            ]);
            $q->sortby('id', 'DESC');
            $draft = $this->modx->getObject(msOrder::class, $q);

            if ($draft) {
                // Sync token: update draft token to match current session token
                // This ensures future lookups will find it by token
                $oldToken = $draft->get('token');
                if ($oldToken !== $token) {
                    $draft->set('token', $token);
                    $draft->save();
                    $this->modx->log(
                        modX::LOG_LEVEL_INFO,
                        "[OrderDraftManager] Token synced for customer {$customerId}: draft #{$draft->get('id')}"
                    );
                }
                return $draft;
            }
        }

        return null;
    }

    /**
     * Create new draft order
     */
    public function createDraft(string $token, string $ctx = 'web'): msOrder
    {
        $status_draft = (int) $this->modx->getOption('ms3_status_draft', null, 1) ?: 1;

        /** @var msOrder $msOrder */
        $msOrder = $this->modx->newObject(msOrder::class);
        $data = [
            'token' => $token,
            'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'status_id' => $status_draft,
            'context' => $ctx,
            'createdon' => time(),
            'user_id' => $this->modx->getLoginUserID($ctx),
        ];
        $msOrder->fromArray($data);

        // No draft-specific events in MS3 registry: msOrder::save() already fires
        // msOnBeforeSaveOrder / msOnSaveOrder with MODE_NEW.
        $saved = $msOrder->save();

        if ($saved) {
            // Create associated address record
            $msOrderAddress = $this->modx->newObject(msOrderAddress::class);
            $msOrderAddress->fromArray([
                'createdon' => time(),
                'order_id' => $msOrder->get('id')
            ]);
            $msOrderAddress->save();
        }

        return $msOrder;
    }

    /**
     * Get or create draft order
     *
     * @param string $token Session token
     * @param string $ctx Context key
     * @param int|null $customerId Optional customer ID to link
     * @return msOrder
     */
    public function getOrCreateDraft(string $token, string $ctx = 'web', ?int $customerId = null): msOrder
    {
        $draft = $this->getDraft($token, $ctx);

        if (!$draft) {
            $draft = $this->createDraft($token, $ctx);
        }

        // Link customer if provided and not already linked
        if ($customerId && empty($draft->get('customer_id'))) {
            $draft->set('customer_id', $customerId);
            $draft->save();
        }

        return $draft;
    }

    /**
     * Recalculate and persist draft cart_cost / weight / cost from order products.
     *
     * Plugin hooks: $draft->save() fires msOnBeforeSaveOrder / msOnSaveOrder (see msOrder::save).
     * Display-time cost adjustment uses msOnBeforeGetOrderCost / msOnGetOrderCost via OrderCostCalculator,
     * not this persist path — do not invent a separate "recalculate" event here.
     */
    public function recalculate(msOrder $draft): void
    {
        $totals = OrderService::aggregateProductsTotals($draft->getMany('Products') ?? []);
        $cart_cost = $totals['cart_cost'];
        $weight = $totals['weight'];

        /** @var OrderService $orderService */
        $orderService = $this->modx->services->get('ms3_order_service');
        $delivery_cost = (float) $draft->get('delivery_cost');
        $cost = $orderService->clampComputedTotal($draft, (float) $cart_cost, $delivery_cost, 0.0);

        $draft->set('updatedon', time());
        $draft->set('cart_cost', $cart_cost);
        $draft->set('cost', $cost);
        $draft->set('weight', $weight);
        $draft->save();
    }

    /**
     * Get order data as array (merged order + address fields)
     */
    public function toArray(?msOrder $draft): array
    {
        if (empty($draft)) {
            $output = $this->modx->getFields(msOrder::class);
            $address = $this->modx->getFields(msOrderAddress::class);
            $addressFields = [];
            foreach ($address as $key => $value) {
                $addressFields['address_' . $key] = $value;
            }
            return array_merge($output, $addressFields);
        }

        $address = $draft->getOne('Address');
        $output = $draft->toArray();

        if (!empty($address)) {
            $addressFields = [];
            foreach ($address->toArray() as $key => $value) {
                $addressFields['address_' . $key] = $value;
            }
            $output = array_merge($output, $addressFields);
        }

        return $output;
    }

    /**
     * Clean order data (reset all fields to null)
     *
     * @return true|string True on success, plugin error message otherwise
     */
    public function clean(msOrder $draft): bool|string
    {
        $response = $this->ms3->utils->invokeEvent('msOnBeforeEmptyOrder', [
            'draft' => $draft,
        ]);
        if (!$response['success']) {
            return $response['message'];
        }

        // Clean address fields
        if ($draft->Address) {
            $addressFields = array_keys($draft->Address->toArray());
            foreach ($addressFields as $key) {
                if (!in_array($key, ['id', 'order_id', 'user_id', 'createdon'])) {
                    $draft->Address->set($key, null);
                }
            }
            $draft->Address->save();
        }

        // Clean order fields
        $orderFields = array_keys($draft->toArray());
        foreach ($orderFields as $key) {
            if (!in_array($key, ['id', 'user_id', 'token', 'status_id', 'createdon'])) {
                $draft->set($key, null);
            }
        }

        $draft->set('updatedon', time());
        $draft->save();

        $response = $this->ms3->utils->invokeEvent('msOnEmptyOrder', [
            'draft' => $draft,
        ]);
        if (!$response['success']) {
            return $response['message'];
        }

        return true;
    }

    /**
     * Update draft field
     *
     * @param msOrder $draft Draft order
     * @param string $key Field key
     * @param mixed $value Field value (null to clear)
     * @return bool True if field was updated
     */
    public function updateField(msOrder $draft, string $key, mixed $value = null): bool
    {
        $updated = false;

        $orderFields = array_keys($draft->toArray());
        $addressFields = $draft->Address ? array_keys($draft->Address->toArray()) : [];

        // Update order field
        if (in_array($key, $orderFields)) {
            $draft->set($key, $value);
            $draft->set('updatedon', time());
            $draft->save();
            $updated = true;
        }

        // Update address field
        if (in_array($key, $addressFields)) {
            $draft->Address->set($key, $value);
            $draft->Address->save();
            $draft->set('updatedon', time());
            $draft->save();
            $updated = true;
        }

        // Handle save_address in properties
        if ($key === 'save_address') {
            $properties = $draft->get('properties') ?? [];
            if (!empty($value)) {
                $properties['save_address'] = 1;
            } else {
                unset($properties['save_address']);
            }
            $draft->set('properties', $properties);
            $draft->set('updatedon', time());
            $draft->save();
            $updated = true;
        }

        // Fallback: save non-model fields to properties['_validated']
        if (!$updated) {
            $properties = $draft->get('properties') ?? [];
            $validated = $properties['_validated'] ?? [];

            if ($value === null || $value === '') {
                unset($validated[$key]);
            } else {
                $validated[$key] = $value;
            }

            if (empty($validated)) {
                unset($properties['_validated']);
            } else {
                $properties['_validated'] = $validated;
            }

            $draft->set('properties', $properties);
            $draft->set('updatedon', time());
            $draft->save();
            $updated = true;
        }

        return $updated;
    }

    /**
     * Set delivery cost and recalculate total
     */
    public function setDeliveryCost(msOrder $draft, float $deliveryCost): void
    {
        $cartCost = (float) ($draft->get('cart_cost') ?? 0);

        /** @var OrderService $orderService */
        $orderService = $this->modx->services->get('ms3_order_service');
        $cost = $orderService->clampComputedTotal($draft, $cartCost, $deliveryCost, 0.0);

        $draft->set('delivery_cost', $deliveryCost);
        $draft->set('cost', $cost);
        $draft->save();
    }

    // =========================================================================
    // Methods shared with Cart
    // =========================================================================

    /**
     * Attach customer to draft order
     *
     * @param msOrder $draft Draft order
     * @param int $customerId Customer ID
     * @return bool True if customer was attached
     */
    public function attachCustomer(msOrder $draft, int $customerId): bool
    {
        if ($customerId <= 0) {
            return false;
        }

        if ($draft->get('customer_id') == $customerId) {
            return false; // Already attached
        }

        $draft->set('customer_id', $customerId);
        $draft->set('updatedon', time());
        return $draft->save();
    }

    /**
     * Ensure order has an associated address record
     *
     * @param msOrder $draft Draft order
     * @return msOrderAddress Address object
     */
    public function ensureAddress(msOrder $draft): msOrderAddress
    {
        $address = $draft->getOne('Address');

        if (!$address) {
            $address = $this->modx->newObject(msOrderAddress::class);
            $address->fromArray([
                'order_id' => $draft->get('id'),
                'user_id' => $draft->get('user_id'),
                'createdon' => time(),
            ]);
            $address->save();
        }

        return $address;
    }

    /**
     * Sync draft token with current session token
     *
     * Used when customer logs in and their cart needs to be linked
     * to the new session token.
     *
     * @param msOrder $draft Draft order
     * @param string $newToken New session token
     * @return bool True if token was changed
     */
    public function syncToken(msOrder $draft, string $newToken): bool
    {
        $oldToken = $draft->get('token');

        if ($oldToken === $newToken) {
            return false;
        }

        $draft->set('token', $newToken);
        $saved = $draft->save();

        if ($saved) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[OrderDraftManager] Token synced: draft #{$draft->get('id')}, {$oldToken} -> {$newToken}"
            );
        }

        return $saved;
    }

    /**
     * Get draft by customer ID (without token)
     *
     * Useful for cart recovery when customer logs in.
     *
     * @param int $customerId Customer ID
     * @param string $ctx Context key
     * @return msOrder|null
     */
    public function getDraftByCustomer(int $customerId, string $ctx = 'web'): ?msOrder
    {
        if ($customerId <= 0) {
            return null;
        }

        $statusDraft = (int) $this->modx->getOption('ms3_status_draft', null, 1) ?: 1;

        $q = $this->modx->newQuery(msOrder::class);
        $q->where([
            'customer_id' => $customerId,
            'status_id' => $statusDraft,
            'context' => $ctx,
        ]);
        $q->sortby('id', 'DESC');

        return $this->modx->getObject(msOrder::class, $q);
    }

    /**
     * Delete draft order and all related data
     *
     * @param msOrder $draft Draft order
     * @return bool True if deleted
     */
    public function deleteDraft(msOrder $draft): bool
    {
        // Products are deleted via cascade, but let's be explicit
        $products = $draft->getMany('Products');
        foreach ($products as $product) {
            $product->remove();
        }

        // Address is deleted via cascade
        $address = $draft->getOne('Address');
        if ($address) {
            $address->remove();
        }

        return $draft->remove();
    }

    /**
     * Check if draft has any products
     *
     * @param msOrder $draft Draft order
     * @return bool True if empty
     */
    public function isEmpty(msOrder $draft): bool
    {
        $count = $this->modx->getCount(\MiniShop3\Model\msOrderProduct::class, [
            'order_id' => $draft->get('id'),
        ]);

        return $count === 0;
    }

    /**
     * Get customer ID from session
     *
     * @return int Customer ID or 0
     */
    public function getSessionCustomerId(): int
    {
        return (int)($_SESSION['ms3']['customer_id'] ?? 0);
    }

    /**
     * Bind draft order to customer by token
     *
     * Finds draft with given token and sets customer_id if not already set.
     * Used during login/register to preserve guest cart.
     *
     * @param string $token Session token
     * @param int $customerId Customer ID to bind
     * @param string $ctx Context key
     * @return bool True if draft was found and bound
     */
    public function bindDraftToCustomer(string $token, int $customerId, string $ctx = 'web'): bool
    {
        if (empty($token) || $customerId <= 0) {
            return false;
        }

        $statusDraft = (int)$this->modx->getOption('ms3_status_draft', null, 1) ?: 1;

        $draft = $this->modx->getObject(msOrder::class, [
            'token' => $token,
            'status_id' => $statusDraft,
            'context' => $ctx,
        ]);

        if (!$draft) {
            return true;
        }

        if (!empty($draft->get('customer_id'))) {
            return true;
        }

        $draft->set('customer_id', $customerId);
        if (!$draft->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[OrderDraftManager] Failed to bind draft #{$draft->get('id')} to customer #{$customerId}"
            );
            return false;
        }

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[OrderDraftManager] Bound draft #{$draft->get('id')} to customer #{$customerId}"
        );

        return true;
    }

    /**
     * Move a draft order from one session token to another and bind customer_id.
     *
     * Used after login token rotation so the cart survives while the old token is revoked.
     */
    public function transferDraftToToken(
        string $fromToken,
        string $toToken,
        int $customerId,
        string $ctx = 'web'
    ): bool {
        if ($fromToken === '' || $toToken === '' || $customerId <= 0) {
            return false;
        }

        if ($fromToken === $toToken) {
            return $this->bindDraftToCustomer($toToken, $customerId, $ctx);
        }

        $statusDraft = (int)$this->modx->getOption('ms3_status_draft', null, 1) ?: 1;

        $draft = $this->modx->getObject(msOrder::class, [
            'token' => $fromToken,
            'status_id' => $statusDraft,
            'context' => $ctx,
        ]);

        if (!$draft) {
            return true;
        }

        $existingCustomerId = (int)$draft->get('customer_id');
        if ($existingCustomerId > 0 && $existingCustomerId !== $customerId) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[OrderDraftManager] Refused draft transfer #{$draft->get('id')} "
                . "(owned by customer #{$existingCustomerId})"
            );
            return false;
        }

        $draft->set('customer_id', $customerId);
        $draft->set('token', $toToken);
        if (!$draft->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[OrderDraftManager] Failed to transfer draft #{$draft->get('id')} "
                . "to token for customer #{$customerId}"
            );
            return false;
        }

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[OrderDraftManager] Transferred draft #{$draft->get('id')} to customer #{$customerId}"
        );

        return true;
    }

    /**
     * Create a sessionless draft for programmatic / integration callers (#507).
     *
     * Does not read PHP session or cart tokens.
     *
     * @param array{
     *     context?: string,
     *     customer_id?: int,
     *     delivery_id?: int,
     *     payment_id?: int,
     *     delivery_cost?: float|int,
     *     order_comment?: string,
     *     idempotency_key?: string,
     *     origin?: string,
     *     properties?: array<string, mixed>
     * } $data
     */
    public function createSessionlessDraft(array $data): msOrder
    {
        $ctx = trim((string) ($data['context'] ?? 'web')) ?: 'web';
        $deliveryCost = (float) ($data['delivery_cost'] ?? 0);
        $origin = OrderOrigin::normalize(
            $data['origin'] ?? OrderOrigin::INTEGRATION,
            OrderOrigin::INTEGRATION
        );
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

        /** @var msOrder $order */
        $order = $this->modx->newObject(msOrder::class);
        $order->set('uuid', Uuid::uuid4()->toString());
        $order->set('token', md5(uniqid('ms3_int_', true)));
        $order->set('status_id', (int) $this->modx->getOption('ms3_status_draft', null, 1) ?: 1);
        $order->set('context', $ctx);
        $order->set('createdon', time());
        $order->set('updatedon', time());
        $order->set('user_id', 0);
        $order->set('customer_id', (int) ($data['customer_id'] ?? 0));
        $order->set('delivery_id', (int) ($data['delivery_id'] ?? 0));
        $order->set('payment_id', (int) ($data['payment_id'] ?? 0));
        $order->set('order_comment', (string) ($data['order_comment'] ?? ''));
        $order->set('num', null);
        $order->set('cart_cost', 0);
        $order->set('delivery_cost', $deliveryCost);
        $order->set('weight', 0);
        $order->set('idempotency_key', $idempotencyKey !== '' ? $idempotencyKey : null);

        /** @var OrderService $orderService */
        $orderService = $this->modx->services->get('ms3_order_service');
        $order->set('cost', $orderService->clampComputedTotal(null, 0.0, $deliveryCost, 0.0));

        $properties = is_array($data['properties'] ?? null) ? $data['properties'] : [];
        if ($idempotencyKey !== '') {
            $properties[OrderOrigin::PROPERTY_IDEMPOTENCY_KEY] = $idempotencyKey;
        }
        $properties[OrderOrigin::PROPERTY_ORIGIN] = $origin;
        $order->set('properties', $properties);

        if (!$order->save()) {
            throw new \RuntimeException('ms3_order_err_save');
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $addressData
     */
    public function fillAddressFromArray(msOrder $order, array $addressData): void
    {
        /** @var msOrderAddress $address */
        $address = $this->modx->newObject(msOrderAddress::class);
        $address->set('order_id', $order->get('id'));
        $address->set('createdon', time());

        foreach ([
            'first_name', 'last_name', 'phone', 'email',
            'country', 'index', 'region', 'city', 'metro',
            'street', 'building', 'entrance', 'floor', 'room',
            'comment', 'text_address',
        ] as $field) {
            if (array_key_exists($field, $addressData)) {
                $address->set($field, $addressData[$field]);
            }
        }

        if (!$address->save()) {
            throw new \RuntimeException('ms3_order_err_address_save');
        }
    }

    /**
     * Persist an order product from a caller-supplied snapshot (catalog id and/or price).
     *
     * @param array<string, mixed> $snapshot
     */
    public function addProductFromSnapshot(msOrder $order, array $snapshot, string $origin = OrderOrigin::INTEGRATION): void
    {
        $productId = (int) ($snapshot['product_id'] ?? 0);
        $count = max(1, (int) ($snapshot['count'] ?? 1));
        $name = isset($snapshot['name']) ? (string) $snapshot['name'] : '';
        $price = array_key_exists('price', $snapshot) ? (float) $snapshot['price'] : null;
        $weight = array_key_exists('weight', $snapshot) ? (float) $snapshot['weight'] : null;
        $options = $snapshot['options'] ?? null;
        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($options)) {
            $options = [];
        }

        if ($productId > 0) {
            $product = $this->modx->getObject(msProduct::class, $productId);
            if (!$product) {
                throw new \InvalidArgumentException('ms3_order_err_product_nf');
            }
            if ($name === '') {
                $name = (string) $product->get('pagetitle');
            }
            /** @var msProductData|null $productData */
            $productData = $this->modx->getObject(msProductData::class, $productId);
            if ($price === null) {
                $price = $productData ? (float) $productData->get('price') : 0.0;
            }
            if ($weight === null) {
                $weight = $productData ? (float) $productData->get('weight') : 0.0;
            }
        } elseif ($name === '' || $price === null) {
            throw new \InvalidArgumentException('ms3_order_err_product_snapshot');
        } else {
            $weight = $weight ?? 0.0;
        }

        /** @var msOrderProduct $orderProduct */
        $orderProduct = $this->modx->newObject(msOrderProduct::class);
        $orderProduct->set('order_id', $order->get('id'));
        $orderProduct->set('product_id', $productId > 0 ? $productId : null);
        $orderProduct->set('product_key', md5($productId . json_encode($options)));
        $orderProduct->set('name', $name);
        $orderProduct->set('count', $count);
        $orderProduct->set('price', $price);
        $orderProduct->set('weight', $weight);
        $orderProduct->set('cost', $count * $price);
        $orderProduct->set('options', $options !== [] ? json_encode($options) : null);

        $modeNew = defined(modSystemEvent::class . '::MODE_NEW')
            ? modSystemEvent::MODE_NEW
            : 'new';

        $eventContext = [
            'mode' => $modeNew,
            'object' => $orderProduct,
            'msOrderProduct' => $orderProduct,
            'msOrder' => $order,
            'origin' => OrderOrigin::normalize($origin, OrderOrigin::INTEGRATION),
        ];

        $response = $this->ms3->utils->invokeEvent('msOnBeforeCreateOrderProduct', $eventContext);
        if (!$response['success']) {
            throw new \RuntimeException((string) $response['message']);
        }

        if (!$orderProduct->save()) {
            throw new \RuntimeException('ms3_order_err_product_save');
        }

        $this->ms3->utils->invokeEvent('msOnCreateOrderProduct', $eventContext);
    }
}
