<?php
/**
 * Orders grid filters configuration
 *
 * Filter types:
 * - text: Text input for search
 * - select: Dropdown with options from model, API, or static list
 * - datepicker: Single date picker
 * - daterange: Date range picker (from/to)
 *
 * Source types for select:
 * - model: Load options from xPDO model class
 * - api: Load options from API endpoint (resolved on frontend)
 * - static: Static options array
 *
 * To customize: copy this file to custom/filters/orders.php and modify.
 * Custom config will be merged with default (custom overrides default).
 *
 * @see \MiniShop3\Services\FilterConfigManager
 */

return [
    // Text search across multiple fields
    'query' => [
        'type' => 'text',
        'label' => 'search',
        'placeholder' => 'search_placeholder',
        'fields' => ['num', 'email', 'phone'],
        'operator' => 'like',
        'width' => '250px',
        'position' => 10,
    ],

    // Status filter
    'status_id' => [
        'type' => 'select',
        'label' => 'order_status',
        'placeholder' => 'all',
        'source' => [
            'type' => 'model',
            'class' => 'MiniShop3\\Model\\msOrderStatus',
            'valueField' => 'id',
            'labelField' => 'name',
            'where' => ['active' => true],
            'sort' => ['rank' => 'ASC'],
        ],
        'width' => '180px',
        'position' => 20,
    ],

    // Delivery method filter
    'delivery_id' => [
        'type' => 'select',
        'label' => 'order_delivery',
        'placeholder' => 'all',
        'source' => [
            'type' => 'model',
            'class' => 'MiniShop3\\Model\\msDelivery',
            'valueField' => 'id',
            'labelField' => 'name',
            'where' => ['active' => true],
            'sort' => ['rank' => 'ASC'],
        ],
        'width' => '180px',
        'position' => 30,
    ],

    // Payment method filter
    'payment_id' => [
        'type' => 'select',
        'label' => 'order_payment',
        'placeholder' => 'all',
        'source' => [
            'type' => 'model',
            'class' => 'MiniShop3\\Model\\msPayment',
            'valueField' => 'id',
            'labelField' => 'name',
            'where' => ['active' => true],
            'sort' => ['rank' => 'ASC'],
        ],
        'width' => '180px',
        'position' => 40,
    ],

    // Date range filter for order creation date
    'createdon' => [
        'type' => 'daterange',
        'label' => 'order_createdon',
        'fields' => [
            'from' => 'createdon_from',
            'to' => 'createdon_to',
        ],
        'width' => '280px',
        'position' => 50,
    ],

    // Context filter (hidden by default, useful for multi-context sites)
    'context_key' => [
        'type' => 'select',
        'label' => 'order_context',
        'placeholder' => 'all',
        'source' => [
            'type' => 'model',
            'class' => 'MODX\\Revolution\\modContext',
            'valueField' => 'key',
            'labelField' => 'name',
            'where' => ['key:!=' => 'mgr'],
            'sort' => ['key' => 'ASC'],
        ],
        'width' => '150px',
        'position' => 60,
        'visible' => false,
    ],
];
