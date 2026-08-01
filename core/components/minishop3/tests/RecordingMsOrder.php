<?php

declare(strict_types=1);

namespace MiniShop3\Tests;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;

/**
 * Mutable msOrder stand-in for Level-2 cart/order tests (skips xPDO map bootstrap).
 */
final class RecordingMsOrder extends msOrder
{
    /** @var array<string, mixed> */
    private array $fields;

    /** @var list<object> */
    public array $products = [];

    public bool $saved = false;

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    public function get($key)
    {
        return $this->fields[$key] ?? null;
    }

    public function set($key, $value)
    {
        $this->fields[$key] = $value;

        return true;
    }

    public function getMany($alias, $criteria = null, $cacheFlag = true)
    {
        return $alias === 'Products' ? $this->products : [];
    }

    public function addMany($objects, $alias = '')
    {
        if ($alias !== 'Products') {
            return true;
        }
        $list = is_array($objects) ? $objects : [$objects];
        foreach ($list as $object) {
            $this->products[] = $object;
            if ($object instanceof msOrderProduct && method_exists($object, 'set')) {
                $object->set('order_id', $this->get('id'));
            }
        }

        return true;
    }

    public function save($cacheFlag = null)
    {
        $this->saved = true;
        foreach ($this->products as $product) {
            if (!is_object($product) || !method_exists($product, 'save')) {
                continue;
            }
            // Persist only new lines. Re-saving attached instances after an out-of-band
            // DB update would overwrite fresh rows with stale in-memory fields.
            if (method_exists($product, 'get') && !empty($product->get('id'))) {
                continue;
            }
            $product->save();
        }

        return true;
    }
}
