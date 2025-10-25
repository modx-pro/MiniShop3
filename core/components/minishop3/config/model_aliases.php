<?php

/**
 * Маппинг alias → model class для универсальной работы с моделями
 *
 * Используется в API endpoints для получения полей моделей через короткие алиасы
 * Например: /api/mgr/models/product_data/fields вместо передачи полного класса
 *
 * @return array
 */

return [
    // Товары
    'product_data' => 'MiniShop3\\Model\\msProductData',

    // Заказы
    'order' => 'MiniShop3\\Model\\msOrder',
    'order_product' => 'MiniShop3\\Model\\msOrderProduct',

    // Производители
    'vendor' => 'MiniShop3\\Model\\msVendor',

    // Доставка и оплата
    'delivery' => 'MiniShop3\\Model\\msDelivery',
    'payment' => 'MiniShop3\\Model\\msPayment',
];
