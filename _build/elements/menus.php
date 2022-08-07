<?php

return [
    'miniShop3' => [
        'description' => 'ms_menu_desc',
        'icon' => '<i class="icon-shopping-cart icon icon-large"></i>',
        'action' => 'mgr/orders',
    ],
    'ms_orders' => [
        'description' => 'ms_orders_desc',
        'parent' => 'miniShop3',
        'menuindex' => 0,
        'action' => 'mgr/orders',
    ],
    'ms_settings' => [
        'description' => 'ms_settings_desc',
        'parent' => 'miniShop3',
        'menuindex' => 1,
        'action' => 'mgr/settings',
    ],
    'ms_system_settings' => [
        'description' => 'ms_system_settings_desc',
        'parent' => 'miniShop3',
        'menuindex' => 2,
        'namespace' => 'core',
        'permissions' => 'settings',
        'action' => 'system/settings',
        'params' => '&ns=minishop3',
    ],
];
