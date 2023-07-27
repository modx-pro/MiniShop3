<?php

return [
    'miniShopManagerPolicyTemplate' => [
        'description' => 'A policy for MiniShop3 managers.',
        'template_group' => 1,
        'permissions' => [
            'mscategory_save' => [],
            'msproduct_save' => [],
            'msproduct_publish' => [],
            'msproduct_delete' => [],
            'msorder_save' => [],
            'msorder_view' => [],
            'msorder_list' => [],
            'msorder_remove' => [],
            'mssetting_save' => [],
            'mssetting_view' => [],
            'mssetting_list' => [],
            'msproductfile_save' => [],
            'msproductfile_generate' => [],
            'msproductfile_list' => [],
        ],
    ],
];
