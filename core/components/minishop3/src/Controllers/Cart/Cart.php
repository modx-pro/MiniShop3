<?php

namespace MiniShop3\Controllers\Cart;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Cart\CartDraftContext;
use MiniShop3\Services\Cart\CartItemManager;
use MiniShop3\Services\Cart\CartMutationHandler;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderLogService;
use MODX\Revolution\modX;

/**
 * Domain facade for the shopping cart (not an HTTP controller).
 *
 * Lives under Controllers\ for MS2-style compatibility, but does not handle
 * FastRoute requests. HTTP entry points are Controllers\Api\Web\CartController
 * (and similar). Registered as DI key `ms3_cart`; typically reached via `$ms3->cart`.
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
 * 3. Register it for DI key `ms3_cart` via `core/config/ms3.services.php`
 *    or `core/config/ms3.services.d/*.php` (see ms3.services.example.php)
 *
 * @package MiniShop3\Controllers\Cart
 * @see \MiniShop3\Controllers\Api\Web\CartController
 * @see \MiniShop3\ServiceRegistry
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
    protected CartMutationHandler $mutationHandler;
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

        $this->mutationHandler = $this->getServiceFromDI(
            'ms3_cart_mutation_handler',
            fn() => new CartMutationHandler(
                $this->modx,
                $this->ms3,
                $this->draftManager,
                $this->itemManager,
                $this->getOrderLog()
            )
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

        $this->ctx = CartDraftContext::resolve($this->modx, $ctx);
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

        $response = $this->mutationHandler->add($this, $this->draft, $this->cart, $id, $count, $options);

        return $this->applyMutationResponse($response);
    }

    /**
     * Change product quantity in cart
     */
    public function change(string $productKey, int $count): array
    {
        return $this->mutateExistingDraft(
            fn(msOrder $draft, array $cart) => $this->mutationHandler->change(
                $this,
                $draft,
                $cart,
                $productKey,
                $count
            )
        );
    }

    /**
     * Change product options in cart
     */
    public function changeOption(string $productKey, array $options): array
    {
        return $this->mutateExistingDraft(
            fn(msOrder $draft, array $cart) => $this->mutationHandler->changeOption(
                $this,
                $draft,
                $cart,
                $productKey,
                $options
            )
        );
    }

    /**
     * Remove product from cart
     */
    public function remove(string $productKey): array
    {
        return $this->mutateExistingDraft(
            fn(msOrder $draft, array $cart) => $this->mutationHandler->remove($this, $draft, $cart, $productKey)
        );
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
     * Run mutation against an existing draft cart
     */
    protected function mutateExistingDraft(callable $handler): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();

        if (!$this->draft) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        $this->loadCart();

        return $this->applyMutationResponse($handler($this->draft, $this->cart));
    }

    /**
     * Sync facade draft/cart from handler payload (status already owned by handler).
     */
    protected function applyMutationResponse(array $response): array
    {
        $data = $response['data'] ?? [];

        if (array_key_exists('draft', $data)) {
            $this->draft = $data['draft'];
            unset($data['draft']);
        }
        if (array_key_exists('cart', $data)) {
            $this->cart = $data['cart'];
        }

        $response['data'] = $data;

        return $response;
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
