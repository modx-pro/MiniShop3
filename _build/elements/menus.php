<?php

return [
    'miniShop3' => [
        'description' => 'ms3_menu_desc',
        'icon' => '<i class="icon-shopping-cart icon icon-large"></i>',
        'action' => 'mgr/orders',
    ],
    'ms3_orders' => [
        'description' => 'ms3_orders_desc',
        'parent' => 'miniShop3',
        'menuindex' => 0,
        'action' => 'mgr/orders',
    ],
    'ms3_customers' => [
        'description' => 'ms3_customers_desc',
        'parent' => 'miniShop3',
        'menuindex' => 0,
        'action' => 'mgr/customers',
    ],
    'ms3_notifications' => [
        'description' => 'ms3_notifications_desc',
        'parent' => 'miniShop3',
        'menuindex' => 1,
        'action' => 'mgr/notifications',
    ],
    'ms3_settings' => [
        'description' => 'ms3_settings_desc',
        'parent' => 'miniShop3',
        'menuindex' => 2,
        'action' => 'mgr/settings',
    ],
    'ms3_system_settings' => [
        'description' => 'ms3_system_settings_desc',
        'parent' => 'miniShop3',
        'menuindex' => 3,
        'namespace' => 'core',
        'permissions' => 'settings',
        'action' => 'system/settings',
        'params' => '&ns=minishop3',
    ],
    'ms3_help' => [
        'description' => 'ms3_help_desc',
        'parent' => 'miniShop3',
        'menuindex' => 4,
        'action' => 'mgr/help',
    ],
    'ms3_utilities' => [
        'description' => 'ms3_utilities_desc',
        'parent' => 'miniShop3',
        'menuindex' => 5,
        'action' => 'mgr/utilities',
    ],
];
