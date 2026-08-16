<?php

namespace MiniShop3\Controllers\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Services\Cart\CartDraftContext;
use MiniShop3\Services\Order\OrderAddressManager;
use MiniShop3\Services\Order\OrderCostCalculator;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderFieldManager;
use MiniShop3\Services\Order\OrderLogService;
use MiniShop3\Services\Order\OrderSubmitHandler;
use MiniShop3\Services\Order\OrderUserResolver;
use MODX\Revolution\modX;

/**
 * Domain facade for the order workflow (not an HTTP controller).
 *
 * Lives under Controllers\ for MS2-style compatibility, but does not handle
 * FastRoute requests. HTTP entry points live under Controllers\Api\Web\*
 * (and Manager API where applicable). Registered as DI key `ms3_order`;
 * typically reached via `$ms3->order`.
 *
 * Manages order workflow: draft creation, field updates, cost calculation,
 * and order submission. Delegates business logic to specialized services.
 *
 * This class maintains backward compatibility while internally using
 * the new service-based architecture.
 *
 * @see \MiniShop3\Controllers\Api\Web\OrderController
 * @see \MiniShop3\ServiceRegistry
 */
class Order
{
    protected modX $modx;
    protected MiniShop3 $ms3;
    protected string $ctx;
    protected string $token = '';
    protected ?msOrder $draft = null;

    public array $config = [];
    private array $order = [];

    protected ?OrderLogService $log = null;

    // Services
    protected OrderDraftManager $draftManager;
    protected OrderCostCalculator $costCalculator;
    protected OrderFieldManager $fieldManager;
    protected OrderAddressManager $addressManager;
    protected OrderUserResolver $userResolver;
    protected OrderSubmitHandler $submitHandler;

    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $pageCtx = $ms3->config['ctx'] ?? CartDraftContext::DEFAULT_CONTEXT;
        $this->ctx = CartDraftContext::resolve($this->modx, $pageCtx);
        $this->config = array_merge([], $config);

        $this->modx->lexicon->load('minishop3:cart');
        $this->modx->lexicon->load('minishop3:order');

        // Initialize services
        $this->initializeServices();
    }

    /**
     * Initialize all order services
     *
     * Services are loaded from DI container if available (allows overriding).
     * Falls back to direct instantiation if not registered.
     */
    protected function initializeServices(): void
    {
        // Base services (no dependencies on other order services)
        $this->draftManager = $this->getServiceFromDI(
            'ms3_order_draft_manager',
            fn() => new OrderDraftManager($this->modx, $this->ms3)
        );

        $this->costCalculator = $this->getServiceFromDI(
            'ms3_order_cost_calculator',
            fn() => new OrderCostCalculator($this->modx, $this->ms3)
        );

        $this->userResolver = $this->getServiceFromDI(
            'ms3_order_user_resolver',
            fn() => new OrderUserResolver($this->modx, $this->ms3)
        );

        // Services with dependencies
        $this->fieldManager = $this->getServiceFromDI(
            'ms3_order_field_manager',
            fn() => new OrderFieldManager($this->modx, $this->ms3, $this->draftManager)
        );

        $this->addressManager = $this->getServiceFromDI(
            'ms3_order_address_manager',
            fn() => new OrderAddressManager(
                $this->modx,
                $this->ms3,
                $this->draftManager,
                $this->fieldManager
            )
        );

        $this->submitHandler = $this->getServiceFromDI(
            'ms3_order_submit_handler',
            fn() => new OrderSubmitHandler(
                $this->modx,
                $this->ms3,
                $this->draftManager,
                $this->costCalculator,
                $this->fieldManager,
                $this->addressManager,
                $this->userResolver
            )
        );
    }

    /**
     * Get service from DI container or use fallback factory
     *
     * @param string $serviceKey DI container key
     * @param callable $fallbackFactory Factory function if not in DI
     * @return mixed Service instance
     */
    protected function getServiceFromDI(string $serviceKey, callable $fallbackFactory): mixed
    {
        if ($this->modx->services->has($serviceKey)) {
            return $this->modx->services->get($serviceKey);
        }

        return $fallbackFactory();
    }

    /**
     * Initialize order with a token
     */
    public function initialize(string $token = '', array $config = []): bool
    {
        if (empty($token)) {
            return false;
        }
        $this->token = $token;
        $pageCtx = $this->ms3->config['ctx'] ?? CartDraftContext::DEFAULT_CONTEXT;
        $this->ctx = CartDraftContext::resolve($this->modx, $pageCtx);
        $this->config = array_merge($this->config, $config);

        // Load validation rules from a session
        if (!empty($_SESSION['ms3']['validation']['rules'])) {
            $this->fieldManager->setValidationRules($_SESSION['ms3']['validation']['rules']);
        }
        if (!empty($_SESSION['ms3']['validation']['messages'])) {
            $this->fieldManager->setValidationMessages($_SESSION['ms3']['validation']['messages']);
        }

        $this->log = $this->getServiceFromDI(
            'ms3_order_log',
            fn() => new OrderLogService($this->modx, $this->ms3)
        );
        $this->fieldManager->setLog($this->log);

        return true;
    }

    /**
     * Load an existing draft order (does NOT create a new one)
     */
    public function initDraft(): bool
    {
        if (empty($this->token)) {
            return false;
        }
        if ($this->draft !== null) {
            return true;
        }

        $this->draft = $this->draftManager->getDraft($this->token, $this->ctx);

        if ($this->draft && empty($this->draft->get('customer_id'))) {
            $this->ms3->customer->initialize($this->token);
            $customerResponse = $this->ms3->customer->getFields();
            if ($customerResponse['success'] && !empty($customerResponse['data']['id'])) {
                $this->draft->set('customer_id', $customerResponse['data']['id']);
                $this->draft->save();
            }
        }

        return $this->draft !== null;
    }

    /**
     * Ensure a draft order exists (create if not exists)
     */
    protected function ensureDraft(): bool
    {
        if (empty($this->token)) {
            return false;
        }

        $this->initDraft();

        if (empty($this->draft)) {
            $customerId = null;
            $this->ms3->customer->initialize($this->token);
            $customerResponse = $this->ms3->customer->getFields();
            if ($customerResponse['success'] && !empty($customerResponse['data']['id'])) {
                $customerId = $customerResponse['data']['id'];
            }

            $this->draft = $this->draftManager->getOrCreateDraft($this->token, $this->ctx, $customerId);
        }

        return true;
    }

    /**
     * Get order data
     */
    public function get(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->draft = $this->draftManager->getDraft($this->token, $this->ctx);
        $this->order = $this->draftManager->toArray($this->draft);

        return $this->success('ms3_order_get_success', ['order' => $this->order]);
    }

    /**
     * Get cart cost from draft order
     */
    public function getCartCost(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();
        $this->ensureOrderLoaded();

        return $this->costCalculator->getCartCost($this->draft, $this->token, $this->ctx);
    }

    /**
     * Get delivery cost
     */
    public function getDeliveryCost(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();

        if (!$this->draft) {
            return $this->success('ms3_order_getcost_success', ['cost' => 0]);
        }

        $this->ensureOrderLoaded();

        $response = $this->costCalculator->getDeliveryCost($this->draft, $this->order, $this->token, $this->ctx);

        // Update draft with delivery cost
        if ($response['success'] && $this->draft) {
            $this->draftManager->setDeliveryCost($this->draft, $response['data']['cost']);
        }

        return $response;
    }

    /**
     * Get payment cost
     */
    public function getPaymentCost(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();

        if (!$this->draft) {
            return $this->success('ms3_order_getcost_success', ['cost' => 0]);
        }

        $this->ensureOrderLoaded();

        return $this->costCalculator->getPaymentCost($this->draft, $this->order, $this->token, $this->ctx);
    }

    /**
     * Get total cost (cart + delivery + payment)
     */
    public function getCost(bool $only_cost = false): array
    {
        $this->ensureOrderLoaded();

        return $this->costCalculator->getTotalCost(
            $this->draft,
            $this->order,
            $this->token,
            $this->ctx,
            $only_cost
        );
    }

    /**
     * Add or update order field
     */
    public function add(string $key, mixed $value = null): array
    {
        $this->initDraft();
        $this->ensureOrderLoaded();

        // Handle special case: address_hash
        if ($key === 'address_hash') {
            if (!$this->draft) {
                return $this->success('', [$key => null]);
            }
            return $this->addressManager->setCustomerAddress($this->draft, $this->order, $value);
        }

        return $this->fieldManager->add($this->draft, $this->order, $key, $value);
    }

    /**
     * Validate order field value
     */
    public function validate(string $key, mixed $value): array
    {
        $this->initDraft();
        $this->ensureOrderLoaded();

        return $this->fieldManager->validate($this->order, $key, $value);
    }

    /**
     * Remove order field
     */
    public function remove(string $key): bool
    {
        $this->initDraft();

        if (!$this->draft) {
            return false;
        }

        $this->ensureOrderLoaded();

        return $this->fieldManager->remove($this->draft, $this->order, $key);
    }

    /**
     * Set multiple order fields at once
     *
     * No batch msOn*SetOrder events in MS2/MS3: each field goes through add() and
     * msOnBeforeAddToOrder / msOnAddToOrder (with returnedValues). This method only
     * aggregates per-field failures for the API response.
     */
    public function set(array $order): array
    {
        $this->initDraft();
        $this->ensureOrderLoaded();

        $errors = [];
        foreach ($order as $key => $value) {
            $response = $this->add($key, $value);
            if (!$response['success']) {
                $errors[$key] = $response['message'];
            }
        }

        $this->order = $this->draftManager->toArray($this->draft);

        if (!empty($errors)) {
            return $this->error('ms3_order_err_validation', [
                'order' => $this->order,
                'errors' => $errors,
            ]);
        }

        return $this->success('ms3_order_set_success', ['order' => $this->order]);
    }

    /**
     * Submit order (convert draft to final order)
     */
    public function submit(array $data = []): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();

        if (!$this->draft) {
            return $this->error('ms3_order_err_empty');
        }

        $this->ensureOrderLoaded();

        return $this->submitHandler->submit(
            $this->draft,
            $this->order,
            $this->token,
            $this->ctx,
            $data
        );
    }

    /**
     * Clean order data (reset all fields to null)
     */
    public function clean(): array
    {
        $this->initDraft();

        if (!$this->draft) {
            return $this->success('ms3_order_clean_success');
        }

        $result = $this->draftManager->clean($this->draft);
        if ($result !== true) {
            return $this->error($result !== '' ? $result : 'ms3_err_unknown');
        }

        return $this->success('ms3_order_clean_success');
    }

    /**
     * Recalculate order costs
     */
    public function restrictDraft(msOrder $draft): void
    {
        $this->draftManager->recalculate($draft);
    }

    /**
     * Set customer address from saved addresses
     */
    public function setCustomerAddress(?string $addressHash = null): array
    {
        if (empty($this->token)) {
            return $this->error('');
        }

        $this->ensureOrderLoaded();

        if (!$this->draft) {
            return $this->error('');
        }

        return $this->addressManager->setCustomerAddress($this->draft, $this->order, $addressHash);
    }

    /**
     * Clean customer address fields
     */
    public function cleanCustomerAddress(): array
    {
        if (empty($this->token)) {
            return $this->error('');
        }

        $this->ensureOrderLoaded();

        if (!$this->draft) {
            return $this->error('');
        }

        return $this->addressManager->cleanCustomerAddress($this->draft, $this->order);
    }

    /**
     * Get validation rules for delivery
     */
    public function getDeliveryValidationRules(int $delivery_id = 0): array
    {
        if (empty($delivery_id)) {
            $this->ensureOrderLoaded();
            $delivery_id = $this->order['delivery_id'] ?? 0;
        }

        return $this->fieldManager->getDeliveryValidationRules($delivery_id);
    }

    /**
     * Get required fields for delivery
     */
    public function getDeliveryRequiresFields(int $delivery_id = 0): array
    {
        if (empty($delivery_id)) {
            $this->ensureOrderLoaded();
            $delivery_id = $this->order['delivery_id'] ?? 0;
        }

        return $this->fieldManager->getDeliveryRequiredFields($delivery_id);
    }

    /**
     * Get new order number
     */
    public function getNewOrderNum(): string
    {
        return $this->submitHandler->getNewOrderNum();
    }

    /**
     * Checks accordance of payment and delivery
     */
    public function hasPayment(int $delivery, int $payment): bool
    {
        /** @var \MiniShop3\Services\Delivery\DeliveryService $deliveryService */
        $deliveryService = $this->modx->services->get('ms3_delivery_service');

        return $deliveryService->isPaymentAvailableForDelivery($delivery, $payment);
    }

    /**
     * Returns id for current user. If user does not exist, registers them and returns id.
     */
    public function getUserId(): int
    {
        $this->ensureOrderLoaded();

        return $this->userResolver->getUserId($this->order);
    }

    /**
     * Ensure order array is loaded
     */
    protected function ensureOrderLoaded(): void
    {
        if (empty($this->order)) {
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }
    }

    /**
     * Get current draft (for external access if needed)
     */
    public function getDraft(): ?msOrder
    {
        return $this->draft;
    }

    /**
     * Shorthand for MS3 success method
     */
    protected function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }

    /**
     * Shorthand for MS3 error method
     */
    protected function error(?string $message = '', array $data = [], array $placeholders = []): array
    {
        if ($message === null || $message === '') {
            $message = 'ms3_err_unknown';
        }

        return $this->ms3->utils->error($message, $data, $placeholders);
    }
}
