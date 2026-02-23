<?php

$permissions = [
    'mscategory_save',
    'msproduct_save',
    'msproduct_publish',
    'msproduct_delete',
    'msorder_save',
    'msorder_view',
    'msorder_list',
    'msorder_remove',
    'mssetting_save',
    'mssetting_view',
    'mssetting_list',
    'msproductfile_save',
    'msproductfile_generate',
    'msproductfile_list',
];

return [
    'miniShopManagerPolicy' => [
        'description' => 'A policy for create and update MiniShop3 categories and products.',
        'parent' => 0,
        'class' => '',
        'lexicon' => 'minishop3:permissions',
        'data' => array_fill_keys($permissions, true),
    ],
];
