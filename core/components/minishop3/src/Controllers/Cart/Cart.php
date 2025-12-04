<?php

namespace MiniShop3\Controllers\Cart;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;

/**
 * Shopping cart controller
 *
 * Manages customer cart: adding, changing, removing products.
 * Cart is stored in DB as draft order (msOrder with draft status).
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
    /** @var modX */
    public modX $modx;

    /** @var MiniShop3 */
    public MiniShop3 $ms3;

    /** @var array */
    public array $config = [];

    /** @var string */
    protected string $ctx = 'web';

    /** @var string */
    protected string $token = '';

    /** @var msOrder|null */
    protected ?msOrder $draft = null;

    /** @var array */
    protected array $cart = [];

    /**
     * @param MiniShop3 $ms3
     * @param array $config
     */
    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;

        $this->config = array_merge([
            'max_count' => (int)$this->modx->getOption('ms3_cart_max_count', null, 1000, true),
            'allow_deleted' => false,
            'allow_unpublished' => false,
            'cart_product_key_fields' => $this->modx->getOption(
                'ms3_cart_product_key_fields',
                null,
                'id,options',
                true
            ),
        ], $config);

        $this->modx->lexicon->load('minishop3:cart');
    }

    /**
     * Initialize cart for context
     *
     * @param string $ctx MODX context (web, mgr, etc.)
     * @param string $token Customer token
     * @return bool
     */
    public function initialize(string $ctx = 'web', string $token = ''): bool
    {
        if (empty($token)) {
            return false;
        }

        $ms3_cart_context = (bool)$this->modx->getOption('ms3_cart_context', null, '0', true);
        $this->ctx = $ms3_cart_context ? 'web' : $ctx;
        $this->token = $token;

        return true;
    }

    /**
     * Get cart
     *
     * @return array Response ['success' => bool, 'message' => '', 'data' => ['cart' => [], 'status' => []]]
     */
    public function get(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        $response = $this->invokeEvent('msOnBeforeGetCart', [
            'draft' => $this->draft,
        ]);
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
     *
     * @param int $id Product ID
     * @param int $count Quantity
     * @param array $options Product options ['color' => 'red', 'size' => 'L']
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function add(int $id, int $count = 1, array $options = []): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        if (empty($id) || !is_numeric($id)) {
            return $this->error('ms3_cart_add_err_id');
        }

        $count = (int)$count;
        $options = $this->normalizeOptions($options);

        if ($count > $this->config['max_count'] || $count <= 0) {
            return $this->error('ms3_cart_add_err_count', $this->getStatus(), ['count' => $count]);
        }

        $product = $this->validateProduct($id);
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

        $product_key = $this->getProductKey($product->toArray(), $options);
        if (isset($this->cart[$product_key])) {
            return $this->change($product_key, $this->cart[$product_key]['count'] + $count);
        }

        $cartItem = $this->createCartItem($product, $count, $options, $product_key);

        $this->draft->addMany($cartItem, 'Products');
        $this->draft->save();

        $this->recalculateDraft();
        $this->loadCart();

        $response = $this->invokeEvent('msOnAddToCart', [
            'msProduct' => $product,
            'count' => $count,
            'options' => $options,
            'product_key' => $product_key,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        return $this->success('ms3_cart_add_success', [
            'last_key' => $product_key,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ], ['count' => $count]);
    }

    /**
     * Change product quantity in cart
     *
     * @param string $product_key Unique product key in cart
     * @param int $count New quantity
     * @return array Response
     */
    public function change(string $product_key, int $count): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        if (!isset($this->cart[$product_key])) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        $count = (int)$count;

        if ($count <= 0) {
            return $this->remove($product_key);
        }

        if ($count > $this->config['max_count']) {
            return $this->error('ms3_cart_add_err_count', $this->getStatus(), ['count' => $count]);
        }

        $response = $this->invokeEvent('msOnBeforeChangeInCart', [
            'product_key' => $product_key,
            'count' => $count,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $count = $response['data']['count'];

        $this->updateCartItemCount($product_key, $count);
        $this->recalculateDraft();
        $this->loadCart();

        $this->invokeEvent('msOnChangeInCart', [
            'product_key' => $product_key,
            'count' => $count,
        ]);

        return $this->success('ms3_cart_change_success', [
            'last_key' => $product_key,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ], ['count' => $count]);
    }

    /**
     * Change product options in cart
     *
     * @param string $product_key Unique product key
     * @param array $options New options
     * @return array Response
     */
    public function changeOption(string $product_key, array $options): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        if (!isset($this->cart[$product_key])) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        if (empty($options)) {
            return $this->error('ms3_cart_change_options_error', $this->getStatus());
        }

        $response = $this->invokeEvent('msOnBeforeChangeOptionsInCart', [
            'product_key' => $product_key,
            'options' => $options,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $count = 0;
        $newProductKey = $product_key;

        /** @var msOrderProduct $product */
        foreach ($this->draft->getMany('Products') as $product) {
            if ($product_key === $product->get('product_key')) {
                $orderProductOptions = $product->get('options') ?? [];
                $count = $product->get('count');

                foreach ($options as $key => $value) {
                    if (!empty($value)) {
                        $orderProductOptions[$key] = $value;
                    } else {
                        unset($orderProductOptions[$key]);
                    }
                }

                $newProductKey = $this->getProductKey($product->Product->toArray(), $orderProductOptions);

                if ($newProductKey !== $product_key && isset($this->cart[$newProductKey])) {
                    $product->remove();
                    return $this->change($newProductKey, $this->cart[$newProductKey]['count'] + $count);
                }

                $product->set('product_key', $newProductKey);
                $product->set('options', $orderProductOptions);
                $product->save();
                break;
            }
        }

        $this->draft->save();
        $this->recalculateDraft();
        $this->loadCart();

        $this->invokeEvent('msOnChangeOptionInCart', [
            'old_product_key' => $product_key,
            'product_key' => $newProductKey,
            'options' => $options,
        ]);

        return $this->success('ms3_cart_change_success', [
            'last_key' => $newProductKey,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ], ['count' => $count]);
    }

    /**
     * Remove product from cart
     *
     * @param string $product_key Unique product key
     * @return array Response
     */
    public function remove(string $product_key): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        if (!isset($this->cart[$product_key])) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        $response = $this->invokeEvent('msOnBeforeRemoveFromCart', [
            'product_key' => $product_key,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $this->removeCartItem($product_key);

        if ($this->isCartEmpty()) {
            $this->draft->remove();
            $this->draft = null;
            $this->cart = [];
        } else {
            $this->recalculateDraft();
            $this->loadCart();
        }

        $this->invokeEvent('msOnRemoveFromCart', [
            'product_key' => $product_key,
        ]);

        return $this->success('ms3_cart_remove_success', [
            'last_key' => $product_key,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ]);
    }

    /**
     * Clear cart
     *
     * @return array Response
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
            $this->draft->remove();
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
     *
     * @param array $data Additional data to merge with status
     * @return array Response
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
     *
     * @param array $cart Products array
     * @return void
     */
    public function set(array $cart = []): void
    {
        // TODO: Implement if needed
    }

    /**
     * Generate unique product key in cart
     *
     * Key is generated based on fields specified in ms3_cart_product_key_fields
     * Default: id + options (product with different options = different cart positions)
     *
     * @param array $product Product data array
     * @param array $options Product options
     * @return string Unique key (e.g. "ms3d41d8cd98f00b204e9800998ecf8427e")
     */
    public function getProductKey(array $product, array $options = []): string
    {
        $key_fields = array_map('trim', explode(',', $this->config['cart_product_key_fields']));
        $product['options'] = $options;
        $key = '';

        foreach ($key_fields as $key_field) {
            if (isset($product[$key_field])) {
                $key .= is_array($product[$key_field])
                    ? json_encode($product[$key_field])
                    : $product[$key_field];
            }
        }

        return 'ms' . md5($key);
    }

    /**
     * Initialize draft order (create if not exists)
     *
     * @return void
     */
    protected function initDraft(): void
    {
        if ($this->draft !== null) {
            return;
        }

        $this->draft = $this->getDraft();
        if (!$this->draft) {
            $this->draft = $this->createDraft();
        }

        if (empty($this->draft->get('customer_id'))) {
            $this->attachCustomer();
        }
    }

    /**
     * Get existing draft order
     *
     * @return msOrder|null
     */
    protected function getDraft(): ?msOrder
    {
        $status_draft = $this->modx->getOption('ms3_status_draft', null, 1);
        return $this->modx->getObject(msOrder::class, [
            'token' => $this->token,
            'status_id' => $status_draft,
            'context' => $this->ctx,
        ]);
    }

    /**
     * Create new draft order
     *
     * @return msOrder
     */
    protected function createDraft(): msOrder
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = '';
        foreach ($backtrace as $trace) {
            if (!empty($trace['file'])) {
                $caller .= basename($trace['file']) . ':' . ($trace['line'] ?? '?') . ' -> ';
            }
        }
        $this->modx->log(
            \MODX\Revolution\modX::LOG_LEVEL_ERROR,
            "[Cart::createDraft] Creating new draft order. Token: {$this->token}, Caller: {$caller}"
        );

        $status_draft = $this->modx->getOption('ms3_status_draft', null, 1);

        /** @var msOrder $draft */
        $draft = $this->modx->newObject(msOrder::class);
        $draft->fromArray([
            'token' => $this->token,
            'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'status_id' => $status_draft,
            'createdon' => time(),
            'context' => $this->ctx,
            'user_id' => $this->modx->getLoginUserID($this->ctx),
        ]);

        $draft->save();

        $this->createOrderAddress($draft);

        return $draft;
    }

    /**
     * Create delivery address for order
     *
     * @param msOrder $draft Draft order
     * @return void
     */
    protected function createOrderAddress(msOrder $draft): void
    {
        /** @var msOrderAddress $address */
        $address = $this->modx->newObject(msOrderAddress::class);
        $address->fromArray([
            'createdon' => time(),
            'user_id' => $this->modx->getLoginUserID($this->ctx),
            'order_id' => $draft->get('id'),
        ]);
        $address->save();
    }

    /**
     * Attach customer to draft order
     *
     * @return void
     */
    protected function attachCustomer(): void
    {
        $this->ms3->customer->initialize($this->token);
        $customerResponse = $this->ms3->customer->getFields();

        if ($customerResponse['success'] && !empty($customerResponse['data']['id'])) {
            $customer = $customerResponse['data'];
            $this->draft->set('customer_id', $customer['id']);
            $this->draft->save();
        }
    }

    /**
     * Load cart from draft into array
     *
     * @return void
     */
    protected function loadCart(): void
    {
        $this->cart = [];

        if (!$this->draft) {
            return;
        }

        /** @var msOrderProduct $item */
        foreach ($this->draft->getMany('Products') as $item) {
            $key = $item->get('product_key');
            $this->cart[$key] = $item->toArray();
        }
    }

    /**
     * Validate product (existence, publication, deletion)
     *
     * @param int $id Product ID
     * @return msProduct|null
     */
    protected function validateProduct(int $id): ?msProduct
    {
        $filter = ['id' => $id, 'class_key' => msProduct::class];

        if (!$this->config['allow_deleted']) {
            $filter['deleted'] = 0;
        }
        if (!$this->config['allow_unpublished']) {
            $filter['published'] = 1;
        }

        return $this->modx->getObject(msProduct::class, $filter);
    }

    /**
     * Create cart item (msOrderProduct)
     *
     * @param msProduct $product Product
     * @param int $count Quantity
     * @param array $options Options
     * @param string $product_key Product key
     * @return msOrderProduct
     */
    protected function createCartItem(msProduct $product, int $count, array $options, string $product_key): msOrderProduct
    {
        $price = $product->getPrice();
        $old_price = $product->get('old_price');
        $weight = $product->getWeight();

        $discount_price = $old_price > 0 ? $old_price - $price : 0;

        /** @var msOrderProduct $item */
        $item = $this->modx->newObject(msOrderProduct::class);
        $item->fromArray([
            'product_id' => $product->get('id'),
            'product_key' => $product_key,
            'name' => $product->get('pagetitle'),
            'count' => $count,
            'price' => $price,
            'weight' => $weight,
            'cost' => $price * $count,
            'options' => $options,
            'properties' => [
                'old_price' => $old_price,
                'discount_price' => $discount_price,
                'discount_cost' => $discount_price * $count,
            ],
        ]);

        return $item;
    }

    /**
     * Update cart item quantity
     *
     * @param string $product_key Product key
     * @param int $count New quantity
     * @return void
     */
    protected function updateCartItemCount(string $product_key, int $count): void
    {
        /** @var msOrderProduct $product */
        foreach ($this->draft->getMany('Products') as $product) {
            if ($product_key === $product->get('product_key')) {
                $price = $product->get('price');
                $product->set('count', $count);
                $product->set('cost', $price * $count);

                $properties = $product->get('properties') ?? [];
                if (isset($properties['discount_price'])) {
                    $properties['discount_cost'] = $properties['discount_price'] * $count;
                    $product->set('properties', $properties);
                }

                $product->save();
                break;
            }
        }

        $this->draft->save();
    }

    /**
     * Remove cart item
     *
     * @param string $product_key Product key
     * @return void
     */
    protected function removeCartItem(string $product_key): void
    {
        /** @var msOrderProduct $product */
        foreach ($this->draft->getMany('Products') as $product) {
            if ($product_key === $product->get('product_key')) {
                $product->remove();
                break;
            }
        }
    }

    /**
     * Check if cart is empty
     *
     * @return bool
     */
    protected function isCartEmpty(): bool
    {
        if (!$this->draft) {
            return true;
        }

        $count = $this->modx->getCount(msOrderProduct::class, [
            'order_id' => $this->draft->get('id')
        ]);

        return $count === 0;
    }

    /**
     * Recalculate draft totals (cart_cost, cost, weight)
     *
     * @return void
     */
    protected function recalculateDraft(): void
    {
        if (!$this->draft) {
            return;
        }

        $cart_cost = 0;
        $weight = 0;

        /** @var msOrderProduct $product */
        foreach ($this->draft->getMany('Products') as $product) {
            $cart_cost += $product->get('cost');
            $weight += $product->get('weight') * $product->get('count');
        }

        $delivery_cost = $this->draft->get('delivery_cost') ?? 0;

        $this->draft->fromArray([
            'updatedon' => time(),
            'cart_cost' => $cart_cost,
            'cost' => $cart_cost + $delivery_cost,
            'weight' => $weight,
        ]);

        $this->draft->save();
    }

    /**
     * Get cart status (totals)
     *
     * @return array Array with totals (total_count, total_cost, total_weight, etc.)
     */
    protected function getStatus(): array
    {
        $status = [
            'total_count' => 0,
            'total_cost' => 0,
            'total_weight' => 0,
            'total_discount' => 0,
            'total_positions' => count($this->cart),
        ];

        foreach ($this->cart as $item) {
            $status['total_count'] += $item['count'];
            $status['total_cost'] += $item['cost'];
            $status['total_weight'] += $item['weight'] * $item['count'];
            $status['total_discount'] += ($item['properties']['discount_price'] ?? 0) * $item['count'];
        }

        $response = $this->invokeEvent('msOnGetStatusCart', [
            'status' => $status,
        ]);

        if ($response['success'] && isset($response['data']['status'])) {
            $status = $response['data']['status'];
        }

        return $status;
    }

    /**
     * Normalize options (convert JSON string to array)
     *
     * @param mixed $options Options (array or JSON string)
     * @return array
     */
    protected function normalizeOptions($options): array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($options) ? $options : [];
    }

    /**
     * Shorthand for success response
     *
     * @param string $message Lexicon key
     * @param array $data Response data
     * @param array $placeholders Message placeholders
     * @return array
     */
    protected function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }

    /**
     * Shorthand for error response
     *
     * @param string $message Lexicon key
     * @param array $data Response data
     * @param array $placeholders Message placeholders
     * @return array
     */
    protected function error(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * Shorthand for event invocation
     *
     * @param string $eventName Event name
     * @param array $params Event parameters
     * @return array
     */
    protected function invokeEvent(string $eventName, array $params = []): array
    {
        $params['controller'] = $this;
        return $this->ms3->utils->invokeEvent($eventName, $params);
    }
}
