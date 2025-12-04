<?php

/**
 * Alias to model class mapping for universal model operations
 *
 * Used in API endpoints to retrieve model fields via short aliases
 * Example: /api/mgr/models/product_data/fields instead of passing full class name
 *
 * @return array
 */

return [
    'product_data' => 'MiniShop3\\Model\\msProductData',
    'order' => 'MiniShop3\\Model\\msOrder',
    'order_product' => 'MiniShop3\\Model\\msOrderProduct',
    'vendor' => 'MiniShop3\\Model\\msVendor',
    'delivery' => 'MiniShop3\\Model\\msDelivery',
    'payment' => 'MiniShop3\\Model\\msPayment',
];
