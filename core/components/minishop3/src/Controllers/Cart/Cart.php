<?php

namespace MiniShop3\Controllers\Cart;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Services\Cart\CartItemManager;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderLogService;
use MODX\Revolution\modX;

/**
 * Shopping Cart Controller (Facade)
 *
 * Manages customer cart: adding, changing, removing products.
 * Cart is stored in DB as draft order (msOrder with draft status).
 *
 * Delegates business logic to specialized services:
 * - OrderDraftManager: draft lifecycle (shared with Order)
 * - CartItemManager: cart item operations
 * - OrderLogService: change logging
 *
 * To override logic:
 * 1. Create your class extending Cart
 * 2. Override required methods (add, remove, change, etc.)
 * 3. Set your class in system setting: ms3_cart_class = Your\Namespace\MyCart
 *
 * @package MiniShop3\Controllers\Cart
 */
class Cart
{
    public modX $modx;
    public MiniShop3 $ms3;
    public array $config = [];

    protected string $ctx = 'web';
    protected string $token = '';
    protected ?msOrder $draft = null;
    protected array $cart = [];

    // Services
    protected OrderDraftManager $draftManager;
    protected CartItemManager $itemManager;
    protected ?OrderLogService $orderLog = null;

    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $this->config = $config;

        $this->modx->lexicon->load('minishop3:cart');

        $this->initializeServices();
    }

    /**
     * Initialize services from DI container
     */
    protected function initializeServices(): void
    {
        $this->draftManager = $this->getServiceFromDI(
            'ms3_order_draft_manager',
            fn() => new OrderDraftManager($this->modx, $this->ms3)
        );

        $this->itemManager = $this->getServiceFromDI(
            'ms3_cart_item_manager',
            fn() => new CartItemManager($this->modx, $this->ms3)
        );
    }

    /**
     * Get service from DI container or use fallback
     */
    protected function getServiceFromDI(string $serviceKey, callable $fallbackFactory): mixed
    {
        if ($this->modx->services->has($serviceKey)) {
            return $this->modx->services->get($serviceKey);
        }
        return $fallbackFactory();
    }

    /**
     * Get OrderLogService (lazy loading)
     */
    protected function getOrderLog(): OrderLogService
    {
        if ($this->orderLog === null) {
            $this->orderLog = $this->getServiceFromDI(
                'ms3_order_log',
                fn() => new OrderLogService($this->modx, $this->ms3)
            );
        }
        return $this->orderLog;
    }

    /**
     * Initialize cart for context
     */
    public function initialize(string $ctx = 'web', string $token = ''): bool
    {
        if (empty($token)) {
            return false;
        }

        $ms3CartContext = (bool)$this->modx->getOption('ms3_cart_context', null, '0', true);
        $this->ctx = $ms3CartContext ? 'web' : $ctx;
        $this->token = $token;

        return true;
    }

    /**
     * Get cart
     */
    public function get(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();

        if (!$this->draft) {
            return $this->success('ms3_cart_get_success', [
                'cart' => [],
                'status' => $this->getStatus(),
            ]);
        }

        $this->loadCart();

        $response = $this->invokeEvent('msOnBeforeGetCart', ['draft' => $this->draft]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $response = $this->invokeEvent('msOnGetCart', [
            'draft' => $this->draft,
            'data' => $this->cart,
        ]);

        if ($response['success'] && isset($response['data']['data'])) {
            $this->cart = $response['data']['data'];
        }

        return $this->success('ms3_cart_get_success', [
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ]);
    }

    /**
     * Add product to cart
     */
    public function add(int $id, int $count = 1, array $options = []): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->ensureDraft();
        $this->loadCart();

        if (empty($id) || !is_numeric($id)) {
            return $this->error('ms3_cart_add_err_id');
        }

        $count = (int)$count;
        $options = $this->itemManager->normalizeOptions($options);

        if (!$this->itemManager->validateCount($count)) {
            return $this->error('ms3_cart_add_err_count', $this->getStatus(), ['count' => $count]);
        }

        $product = $this->itemManager->validateProduct($id);
        if (!$product) {
            return $this->error('ms3_cart_add_err_nf', $this->getStatus());
        }

        $response = $this->invokeEvent('msOnBeforeAddToCart', [
            'msProduct' => $product,
            'count' => $count,
            'options' => $options,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $count = $response['data']['count'];
        $options = $response['data']['options'];

        $productKey = $this->itemManager->generateProductKey($product->toArray(), $options);

        // If product already in cart - increase count
        if (isset($this->cart[$productKey])) {
            return $this->change($productKey, $this->cart[$productKey]['count'] + $count);
        }

        $cartItem = $this->itemManager->addItem($this->draft, $product, $count, $options, $productKey);

        // Log product addition
        $this->getOrderLog()->addEntry(
            $this->draft->get('id'),
            msOrderLog::ACTION_PRODUCTS,
            [
                'operation' => 'add',
                'product_id' => $id,
                'product_name' => $product->get('pagetitle'),
                'count' => $count,
                'price' => $cartItem->get('price'),
                'cost' => $cartItem->get('cost'),
            ]
        );

        $this->draftManager->recalculate($this->draft);
        $this->loadCart();

        $response = $this->invokeEvent('msOnAddToCart', [
            'msProduct' => $product,
            'count' => $count,
            'options' => $options,
            'product_key' => $productKey,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        return $this->success('ms3_cart_add_success', [
            'last_key' => $productKey,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ], ['count' => $count]);
    }

    /**
     * Change product quantity in cart
     */
    public function change(string $productKey, int $count): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();

        if (!$this->draft) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        $this->loadCart();

        if (!isset($this->cart[$productKey])) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        $count = (int)$count;

        if ($count <= 0) {
            return $this->remove($productKey);
        }

        if (!$this->itemManager->validateCount($count)) {
            return $this->error('ms3_cart_add_err_count', $this->getStatus(), ['count' => $count]);
        }

        $response = $this->invokeEvent('msOnBeforeChangeInCart', [
            'product_key' => $productKey,
            'count' => $count,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $count = $response['data']['count'];

        // Store old values for logging
        $oldCount = $this->cart[$productKey]['count'];
        $productId = $this->cart[$productKey]['product_id'];
        $productName = $this->cart[$productKey]['name'] ?? '';

        $this->itemManager->updateItemCount($this->draft, $productKey, $count);

        // Log quantity change
        if ($oldCount != $count) {
            $this->getOrderLog()->addEntry(
                $this->draft->get('id'),
                msOrderLog::ACTION_PRODUCTS,
                [
                    'operation' => 'update',
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'changes' => [
                        'count' => ['old' => $oldCount, 'new' => $count],
                    ],
                ]
            );
        }

        $this->draftManager->recalculate($this->draft);
        $this->loadCart();

        $this->invokeEvent('msOnChangeInCart', [
            'product_key' => $productKey,
            'count' => $count,
        ]);

        return $this->success('ms3_cart_change_success', [
            'last_key' => $productKey,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ], ['count' => $count]);
    }

    /**
     * Change product options in cart
     */
    public function changeOption(string $productKey, array $options): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();

        if (!$this->draft) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        $this->loadCart();

        if (!isset($this->cart[$productKey])) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        if (empty($options)) {
            return $this->error('ms3_cart_change_options_error', $this->getStatus());
        }

        $response = $this->invokeEvent('msOnBeforeChangeOptionsInCart', [
            'product_key' => $productKey,
            'options' => $options,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        if (isset($response['data']['options']) && is_array($response['data']['options'])) {
            $options = $response['data']['options'];
        }

        $count = $this->cart[$productKey]['count'];

        // Check if new key already exists
        $item = $this->itemManager->getItemByKey($this->draft, $productKey);
        if ($item) {
            $currentOptions = $item->get('options') ?? [];
            foreach ($options as $key => $value) {
                if (!empty($value)) {
                    $currentOptions[$key] = $value;
                } else {
                    unset($currentOptions[$key]);
                }
            }

            $product = $item->getOne('Product');
            if ($product) {
                $newProductKey = $this->itemManager->generateProductKey($product->toArray(), $currentOptions);

                // If new key exists, merge quantities into the existing line.
                if ($newProductKey !== $productKey && isset($this->cart[$newProductKey])) {
                    $mergedCount = (int) $this->cart[$newProductKey]['count'] + (int) $count;
                    $item->remove();
                    $result = $this->change($newProductKey, $mergedCount);
                    if (!empty($result['success'])) {
                        $this->invokeEvent('msOnChangeOptionInCart', [
                            'old_product_key' => $productKey,
                            'product_key' => $newProductKey,
                            'options' => $options,
                        ]);
                        // Prefer options lexicon over quantity-change wording.
                        $result = $this->success('ms3_cart_change_options_success', [
                            'last_key' => $newProductKey,
                            'cart' => $result['data']['cart'] ?? $this->cart,
                            'status' => $result['data']['status'] ?? $this->getStatus(),
                        ], ['count' => $mergedCount]);
                    }

                    return $result;
                }
            }
        }

        $newProductKey = $this->itemManager->updateItemOptions($this->draft, $productKey, $options);

        if (!$newProductKey) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        $this->draftManager->recalculate($this->draft);
        $this->loadCart();

        $this->invokeEvent('msOnChangeOptionInCart', [
            'old_product_key' => $productKey,
            'product_key' => $newProductKey,
            'options' => $options,
        ]);

        return $this->success('ms3_cart_change_options_success', [
            'last_key' => $newProductKey,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ], ['count' => $count]);
    }

    /**
     * Remove product from cart
     */
    public function remove(string $productKey): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();

        if (!$this->draft) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        $this->loadCart();

        if (!isset($this->cart[$productKey])) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        $response = $this->invokeEvent('msOnBeforeRemoveFromCart', [
            'product_key' => $productKey,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // Store data for logging
        $itemData = $this->itemManager->getItemDataForLog($this->draft, $productKey);
        $orderId = $this->draft->get('id');

        $this->itemManager->removeItem($this->draft, $productKey);

        // Log product removal
        $this->getOrderLog()->addEntry(
            $orderId,
            msOrderLog::ACTION_PRODUCTS,
            [
                'operation' => 'remove',
                'product_id' => $itemData['product_id'] ?? 0,
                'product_name' => $itemData['product_name'] ?? '',
                'count' => $itemData['count'] ?? 0,
                'price' => $itemData['price'] ?? 0,
            ]
        );

        if ($this->draftManager->isEmpty($this->draft)) {
            $this->draftManager->deleteDraft($this->draft);
            $this->draft = null;
            $this->cart = [];
        } else {
            $this->draftManager->recalculate($this->draft);
            $this->loadCart();
        }

        $this->invokeEvent('msOnRemoveFromCart', [
            'product_key' => $productKey,
        ]);

        return $this->success('ms3_cart_remove_success', [
            'last_key' => $productKey,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ]);
    }

    /**
     * Clear cart
     */
    public function clean(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        $response = $this->invokeEvent('msOnBeforeEmptyCart');
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        if ($this->draft) {
            $this->draftManager->deleteDraft($this->draft);
            $this->draft = null;
        }

        $this->cart = [];

        $this->invokeEvent('msOnEmptyCart');

        return $this->success('ms3_cart_clean_success', [
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ]);
    }

    /**
     * Get cart status
     */
    public function status(array $data = []): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        if (empty($this->cart)) {
            $this->initDraft();
            $this->loadCart();
        }

        $status = array_merge($data, $this->getStatus());

        return $this->success('ms3_cart_status_success', $status);
    }

    /**
     * Set all cart products at once
     */
    public function set(array $cart = []): void
    {
        // TODO: Implement if needed
    }

    /**
     * Generate unique product key in cart
     */
    public function getProductKey(array $product, array $options = []): string
    {
        return $this->itemManager->generateProductKey($product, $options);
    }

    // =========================================================================
    // Internal methods
    // =========================================================================

    /**
     * Load existing draft order
     */
    protected function initDraft(): void
    {
        if ($this->draft !== null) {
            return;
        }

        $this->draft = $this->draftManager->getDraft($this->token, $this->ctx);

        if ($this->draft && empty($this->draft->get('customer_id'))) {
            $this->attachCustomer();
        }
    }

    /**
     * Ensure draft order exists
     */
    protected function ensureDraft(): msOrder
    {
        $this->initDraft();

        if (!$this->draft) {
            $customerId = $this->getCustomerId();
            $this->draft = $this->draftManager->getOrCreateDraft($this->token, $this->ctx, $customerId);
        }

        return $this->draft;
    }

    /**
     * Attach customer to draft
     */
    protected function attachCustomer(): void
    {
        $customerId = $this->getCustomerId();
        if ($customerId && $this->draft) {
            $this->draftManager->attachCustomer($this->draft, $customerId);
        }
    }

    /**
     * Get current customer ID
     */
    protected function getCustomerId(): ?int
    {
        $this->ms3->customer->initialize($this->token);
        $customerResponse = $this->ms3->customer->getFields();

        if ($customerResponse['success'] && !empty($customerResponse['data']['id'])) {
            return (int)$customerResponse['data']['id'];
        }

        return null;
    }

    /**
     * Load cart items into array
     */
    protected function loadCart(): void
    {
        if (!$this->draft) {
            $this->cart = [];
            return;
        }

        $this->cart = $this->itemManager->loadItems($this->draft);
    }

    /**
     * Get cart status (totals)
     */
    protected function getStatus(): array
    {
        $status = $this->itemManager->calculateStatus($this->cart);

        $response = $this->invokeEvent('msOnGetStatusCart', ['status' => $status]);

        if ($response['success'] && isset($response['data']['status'])) {
            $status = $response['data']['status'];
        }

        return $status;
    }

    /**
     * Get current draft
     */
    public function getDraft(): ?msOrder
    {
        return $this->draft;
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    protected function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }

    protected function error(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    protected function invokeEvent(string $eventName, array $params = []): array
    {
        $params['controller'] = $this;
        return $this->ms3->utils->invokeEvent($eventName, $params);
    }
}
