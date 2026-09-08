<?php

namespace MiniShop3\Services\Cart;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msProduct;
use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use MODX\Revolution\modX;

/**
 * Cart Item Manager
 *
 * Manages cart items (products in draft order): add, update, remove, validate.
 * Handles product key generation and cart status calculation.
 *
 * Can be overridden via DI to customize cart behavior.
 */
class CartItemManager
{
    protected modX $modx;
    protected MiniShop3 $ms3;

    /** @var array Configuration */
    protected array $config = [];

    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;

        $this->config = [
            'max_count' => (int)$this->modx->getOption('ms3_cart_max_count', null, 1000, true),
            'allow_deleted' => false,
            'allow_unpublished' => false,
            'cart_product_key_fields' => $this->modx->getOption(
                'ms3_cart_product_key_fields',
                null,
                'id,options',
                true
            ),
        ];
    }

    /**
     * Load all cart items as array
     *
     * Always fetches fresh data from DB (bypasses xPDO relation cache).
     *
     * @param msOrder $draft Draft order
     * @return array Keyed by product_key
     */
    public function loadItems(msOrder $draft): array
    {
        $items = [];

        // Use getIterator() for fresh DB query instead of getMany() which uses cached relations
        /** @var msOrderProduct $item */
        foreach ($this->modx->getIterator(msOrderProduct::class, ['order_id' => $draft->get('id')]) as $item) {
            $key = $item->get('product_key');
            $items[$key] = $item->toArray();
        }

        return $items;
    }

    /**
     * Add product to cart
     *
     * @param msOrder $draft Draft order
     * @param msProduct $product Product to add
     * @param int $count Quantity
     * @param array $options Product options
     * @param string $productKey Product key
     * @return msOrderProduct Created cart item
     */
    public function addItem(
        msOrder $draft,
        msProduct $product,
        int $count,
        array $options,
        string $productKey
    ): msOrderProduct {
        $price = $product->getPrice();
        $oldPrice = $product->get('old_price');
        $weight = $product->getWeight();
        $discountPrice = $oldPrice > 0 ? $oldPrice - $price : 0;

        /** @var msOrderProduct $item */
        $item = $this->modx->newObject(msOrderProduct::class);
        $item->fromArray([
            'product_id' => $product->get('id'),
            'product_key' => $productKey,
            'name' => $product->get('pagetitle'),
            'count' => $count,
            'price' => $price,
            'weight' => $weight,
            'cost' => $price * $count,
            'options' => $options,
            'properties' => [
                'old_price' => $oldPrice,
                'discount_price' => $discountPrice,
                'discount_cost' => $discountPrice * $count,
            ],
        ]);

        $draft->addMany($item, 'Products');
        $draft->save();

        return $item;
    }

    /**
     * Update cart item quantity
     *
     * @param msOrder $draft Draft order
     * @param string $productKey Product key
     * @param int $count New quantity
     * @return bool True if updated
     */
    public function updateItemCount(msOrder $draft, string $productKey, int $count): bool
    {
        $item = $this->getItemByKey($draft, $productKey);

        if (!$item) {
            return false;
        }

        $price = $item->get('price');
        $item->set('count', $count);
        $item->set('cost', $price * $count);

        // Update discount cost in properties
        $properties = $item->get('properties') ?? [];
        if (isset($properties['discount_price'])) {
            $properties['discount_cost'] = $properties['discount_price'] * $count;
            $item->set('properties', $properties);
        }

        return $item->save();
    }

    /**
     * Update cart item options
     *
     * @param msOrder $draft Draft order
     * @param string $productKey Current product key
     * @param array $newOptions New options to merge
     * @return string|null New product key or null on failure
     */
    public function updateItemOptions(msOrder $draft, string $productKey, array $newOptions): ?string
    {
        $item = $this->getItemByKey($draft, $productKey);

        if (!$item) {
            return null;
        }

        $currentOptions = $item->get('options') ?? [];

        // Merge options
        foreach ($newOptions as $key => $value) {
            if (!empty($value)) {
                $currentOptions[$key] = $value;
            } else {
                unset($currentOptions[$key]);
            }
        }

        // Generate new key
        $product = $item->getOne('Product');
        if (!$product) {
            return null;
        }

        $newKey = $this->generateProductKey($product->toArray(), $currentOptions);

        $item->set('product_key', $newKey);
        $item->set('options', $currentOptions);
        $item->save();

        return $newKey;
    }

    /**
     * Remove cart item
     *
     * @param msOrder $draft Draft order
     * @param string $productKey Product key
     * @return bool True if removed
     */
    public function removeItem(msOrder $draft, string $productKey): bool
    {
        $item = $this->getItemByKey($draft, $productKey);

        if (!$item) {
            return false;
        }

        return $item->remove();
    }

    /**
     * Get cart item by product key
     *
     * @param msOrder $draft Draft order
     * @param string $productKey Product key
     * @return msOrderProduct|null
     */
    public function getItemByKey(msOrder $draft, string $productKey): ?msOrderProduct
    {
        return $this->modx->getObject(msOrderProduct::class, [
            'order_id' => $draft->get('id'),
            'product_key' => $productKey,
        ]);
    }

    /**
     * Check if product key exists in cart
     *
     * @param msOrder $draft Draft order
     * @param string $productKey Product key
     * @return bool
     */
    public function hasItem(msOrder $draft, string $productKey): bool
    {
        return $this->getItemByKey($draft, $productKey) !== null;
    }

    /**
     * Get item count for product key
     *
     * @param msOrder $draft Draft order
     * @param string $productKey Product key
     * @return int Current count or 0 if not found
     */
    public function getItemCount(msOrder $draft, string $productKey): int
    {
        $item = $this->getItemByKey($draft, $productKey);
        return $item ? (int)$item->get('count') : 0;
    }

    /**
     * Validate product for adding to cart
     *
     * Checks:
     * - Product exists
     * - Is msProduct class
     * - Not deleted (unless allow_deleted)
     * - Published (unless allow_unpublished)
     *
     * @param int $productId Product ID
     * @return msProduct|null Valid product or null
     */
    public function validateProduct(int $productId): ?msProduct
    {
        if ($productId <= 0) {
            return null;
        }

        $c = $this->modx->newQuery(msProduct::class);
        $c->where([
            'id' => $productId,
            'class_key' => msProduct::class,
        ]);

        if (!$this->config['allow_deleted']) {
            $c->where(['deleted' => 0]);
        }

        if (!$this->config['allow_unpublished']) {
            $c->where(['published' => 1]);
        }

        // Same anonymous RG ACL gate as public catalog (#659) — no cart bypass via product id.
        $context = (string) ($this->modx->context->key ?? 'web');
        if ($context === '') {
            $context = 'web';
        }
        (new CatalogResourceGroupVisibility($this->modx))->apply($c, 'msProduct', $context);

        return $this->modx->getObject(msProduct::class, $c) ?: null;
    }

    /**
     * Validate count value
     *
     * @param int $count Requested count
     * @return bool True if valid
     */
    public function validateCount(int $count): bool
    {
        return $count > 0 && $count <= $this->config['max_count'];
    }

    /**
     * Get max allowed count
     *
     * @return int
     */
    public function getMaxCount(): int
    {
        return $this->config['max_count'];
    }

    /**
     * Generate unique product key
     *
     * Key is based on fields specified in ms3_cart_product_key_fields.
     * Default: id + options (product with different options = different cart positions)
     *
     * @param array $product Product data
     * @param array $options Product options
     * @return string Product key (e.g., "ms3d41d8cd98f00b204e9800998ecf8427e")
     */
    public function generateProductKey(array $product, array $options = []): string
    {
        $keyFields = array_map('trim', explode(',', $this->config['cart_product_key_fields']));
        $product['options'] = $options;
        $keyData = '';

        foreach ($keyFields as $field) {
            if (isset($product[$field])) {
                $keyData .= is_array($product[$field])
                    ? json_encode($product[$field])
                    : $product[$field];
            }
        }

        return 'ms' . md5($keyData);
    }

    /**
     * Normalize options (convert JSON string to array)
     *
     * @param mixed $options Options (array or JSON string)
     * @return array Normalized options
     */
    public static function normalizeOptions(mixed $options): array
    {
        if (!is_string($options)) {
            return is_array($options) ? $options : [];
        }

        $decoded = json_decode($options, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Calculate cart status (totals)
     *
     * @param array $cartItems Cart items array (from loadItems)
     * @return array Status with totals
     */
    public function calculateStatus(array $cartItems): array
    {
        $status = [
            'total_count' => 0,
            'total_cost' => 0,
            'total_weight' => 0,
            'total_discount' => 0,
            'total_positions' => count($cartItems),
        ];

        foreach ($cartItems as $item) {
            $count = (int)($item['count'] ?? 0);
            $status['total_count'] += $count;
            $status['total_cost'] += (float)($item['cost'] ?? 0);
            $status['total_weight'] += (float)($item['weight'] ?? 0) * $count;
            $status['total_discount'] += (float)($item['properties']['discount_price'] ?? 0) * $count;
        }

        return $status;
    }

    /**
     * Get item data for logging
     *
     * @param msOrder $draft Draft order
     * @param string $productKey Product key
     * @return array Item data or empty array
     */
    public function getItemDataForLog(msOrder $draft, string $productKey): array
    {
        $item = $this->getItemByKey($draft, $productKey);

        if (!$item) {
            return [];
        }

        return [
            'product_id' => $item->get('product_id'),
            'product_name' => $item->get('name'),
            'count' => $item->get('count'),
            'price' => $item->get('price'),
            'cost' => $item->get('cost'),
        ];
    }

    /**
     * Set configuration option
     *
     * @param string $key Config key
     * @param mixed $value Config value
     */
    public function setConfig(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Get configuration
     *
     * @return array Full config
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
