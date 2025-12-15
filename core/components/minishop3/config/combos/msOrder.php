<?php
/**
 * Combo field configurations for msOrder model
 *
 * Defines data sources for dropdown/select fields on order forms.
 *
 * Source types:
 * - model: Load options from xPDO model class
 * - static: Static options array
 *
 * To customize: copy this file to custom/combos/msOrder.php and modify.
 * Custom config will be merged with default (custom overrides default).
 *
 * Database config (ms3_model_fields.properties) has highest priority.
 *
 * @see \MiniShop3\Services\ComboConfigManager
 */

return [
    // Order status
    'status_id' => [
        'source' => [
            'type' => 'model',
            'class' => 'MiniShop3\\Model\\msOrderStatus',
            'valueField' => 'id',
            'labelField' => 'name',
            'sort' => ['position' => 'ASC'],
        ],
    ],

    // Delivery method
    'delivery_id' => [
        'source' => [
            'type' => 'model',
            'class' => 'MiniShop3\\Model\\msDelivery',
            'valueField' => 'id',
            'labelField' => 'name',
            'sort' => ['position' => 'ASC'],
        ],
    ],

    // Payment method
    'payment_id' => [
        'source' => [
            'type' => 'model',
            'class' => 'MiniShop3\\Model\\msPayment',
            'valueField' => 'id',
            'labelField' => 'name',
            'sort' => ['position' => 'ASC'],
        ],
    ],

    // Customer
    'customer_id' => [
        'source' => [
            'type' => 'model',
            'class' => 'MiniShop3\\Model\\msCustomer',
            'valueField' => 'id',
            'labelTemplate' => '{first_name} {last_name}',
            'compareField' => 'customer_id',
            'sort' => ['id' => 'DESC'],
        ],
    ],

    // User (MODX user)
    // Uncomment and configure when needed
    // 'user_id' => [
    //     'source' => [
    //         'type' => 'model',
    //         'class' => 'MODX\\Revolution\\modUser',
    //         'valueField' => 'id',
    //         'labelField' => 'username',
    //         'where' => ['active' => true],
    //         'sort' => ['username' => 'ASC'],
    //         'limit' => 100,
    //     ],
    // ],

    // Context (for multi-context sites)
    // 'context_key' => [
    //     'source' => [
    //         'type' => 'model',
    //         'class' => 'MODX\\Revolution\\modContext',
    //         'valueField' => 'key',
    //         'labelField' => 'name',
    //         'where' => ['key:!=' => 'mgr'],
    //         'sort' => ['key' => 'ASC'],
    //     ],
    // ],
];
