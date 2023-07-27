<?php

return [
    'miniShopManagerPolicy' => [
        'description' => 'A policy for create and update MiniShop3 categories and products.',
        'parent' => 0,
        'class' => '',
        'lexicon' => 'minishop3:permissions',
        'data' => json_encode(array(
            'mscategory_save' => true,
            'msproduct_save' => true,
            'msproduct_publish' => true,
            'msproduct_delete' => true,
            'msorder_save' => true,
            'msorder_view' => true,
            'msorder_list' => true,
            'msorder_remove' => true,
            'mssetting_save' => true,
            'mssetting_view' => true,
            'mssetting_list' => true,
            'msproductfile_save' => true,
            'msproductfile_generate' => true,
            'msproductfile_list' => true,
        )),
    ],
];
