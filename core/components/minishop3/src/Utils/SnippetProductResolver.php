<?php

namespace MiniShop3\Utils;

use MiniShop3\Model\msProduct;

/**
 * Resolve a snippet's &product parameter (id or object) without casting an
 * arbitrary object to int (#842 review).
 */
final class SnippetProductResolver
{
    /** @param mixed $product */
    public static function resolveId($product): int
    {
        if ($product instanceof msProduct) {
            return (int) $product->get('id');
        }
        if (is_object($product)) {
            return method_exists($product, 'get') ? (int) $product->get('id') : 0;
        }

        return is_scalar($product) ? (int) $product : 0;
    }
}
