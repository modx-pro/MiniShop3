<?php

return [
    'mgr_tree_icon_mscategory' => [
        'value' => 'icon icon-barcode',
        'xtype' => 'textarea',
        'area' => 'ms3_category',
        'key' => 'mgr_tree_icon_mscategory',
    ],
    'mgr_tree_icon_msproduct' => [
        'value' => 'icon icon-tag',
        'xtype' => 'textarea',
        'area' => 'ms3_product',
        'key' => 'mgr_tree_icon_msproduct',
    ],

    'ms3_services' => [
        'value' => '{"cart":["MiniShop3\\\\Controllers\\\\Cart\\\\Cart"],"order":["MiniShop3\\\\Controllers\\\\Order\\\\Order"],"payment":["MiniShop3\\\\Controllers\\\\Payment\\\\DefaultPayment"],"delivery":["MiniShop3\\\\Controllers\\\\Delivery\\\\DefaultDelivery"]}',
        'xtype' => 'textarea',
        'area' => 'ms3_main',
    ],
    'ms3_plugins' => [
        'value' => '[]',
        'xtype' => 'textarea',
        'area' => 'ms3_main',
    ],
    'ms3_chunks_categories' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_main',
    ],

    'ms3_category_grid_fields' => [
        'value' => 'id,menuindex,pagetitle,article,price,thumb,new,favorite,popular',
        'xtype' => 'textarea',
        'area' => 'ms3_category',
    ],
    'ms3_category_show_nested_products' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_category',
    ],
    'ms3_category_show_options' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_category',
    ],
    'ms3_category_remember_tabs' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_category',
    ],
    'ms3_category_id_as_alias' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_category',
    ],
    'ms3_category_content_default' => [
        'value' => '',
        'xtype' => 'textarea',
        'area' => 'ms3_category',
    ],
    'ms3_template_category_default' => [
        'value' => '',
        'xtype' => 'modx-combo-template',
        'area' => 'ms3_category',
    ],
    'ms3_product_extra_fields' => [
        'value' => 'price,old_price,article,weight,color,size,vendor_id,made_in,tags,new,popular,favorite',
        'xtype' => 'textarea',
        'area' => 'ms3_product',
    ],
    'ms3_template_product_default' => [
        'value' => '',
        'xtype' => 'modx-combo-template',
        'area' => 'ms3_product',
    ],
    'ms3_product_show_in_tree_default' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_product',
    ],
    'ms3_product_source_default' => [
        'value' => 0,
        'xtype' => 'modx-combo-source',
        'area' => 'ms3_product',
    ],
    'ms3_product_thumbnail_default' => [
        'value' => '{assets_url}components/minishop3/img/mgr/ms3_small.png',
        'xtype' => 'textfield',
        'area' => 'ms3_product',
    ],
    'ms3_product_thumbnail_size' => [
        'value' => 'small',
        'xtype' => 'textfield',
        'area' => 'ms3_product',
    ],
    'ms3_product_remember_tabs' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_product',
    ],
    'ms3_product_id_as_alias' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_product',
    ],
    'ms3_price_format' => [
        'value' => '[2, ".", " "]',
        'xtype' => 'textfield',
        'area' => 'ms3_product',
    ],
    'ms3_weight_format' => [
        'value' => '[3, ".", " "]',
        'xtype' => 'textfield',
        'area' => 'ms3_product',
    ],
    'ms3_price_format_no_zeros' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_product',
    ],
    'ms3_weight_format_no_zeros' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_product',
    ],
    'ms3_product_tab_extra' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_product',
    ],
    'ms3_product_tab_gallery' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_product',
    ],
    'ms3_product_tab_links' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_product',
    ],
    'ms3_product_tab_options' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_product',
    ],
    'ms3_product_tab_categories' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_product',
    ],

    'ms3_cart_handler_class' => [
        'value' => 'msCartHandler',
        'xtype' => 'textfield',
        'area' => 'ms3_cart',
    ],
    'ms3_cart_context' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_cart',
    ],
    'ms3_cart_max_count' => [
        'value' => 1000,
        'xtype' => 'numberfield',
        'area' => 'ms3_cart',
    ],
    'ms3_order_format_num' => [
        'value' => 'ym',
        'xtype' => 'textfield',
        'area' => 'ms3_order',
    ],
    'ms3_order_format_num_separator' => [
        'value' => '/',
        'xtype' => 'textfield',
        'area' => 'ms3_order',
    ],
    'ms3_order_grid_fields' => [
        'value' => 'id,num,customer,status,cost,weight,delivery,payment,createdon,updatedon,comment',
        'xtype' => 'textarea',
        'area' => 'ms3_order',
    ],
    'ms3_order_address_fields' => [
        'xtype' => 'textarea',
        'value' => 'first_name,last_name,email,phone,index,country,region,city,metro,street,building,entrance,floor,room,comment,text_address',
        'area' => 'ms3_order',
    ],
    'ms3_order_product_fields' => [
        'xtype' => 'textarea',
        'value' => 'product_pagetitle,vendor_name,product_article,weight,price,count,cost',
        'area' => 'ms3_order',
    ],
    'ms3_order_product_options' => [
        'xtype' => 'textarea',
        'value' => 'size,color',
        'area' => 'ms3_order',
    ],
    'ms3_order_tv_list' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_order',
    ],
    'ms3_order_handler_class' => [
        'value' => 'msOrderHandler',
        'xtype' => 'textfield',
        'area' => 'ms3_order',
    ],
    'ms3_order_user_groups' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_order',
    ],
    'ms3_order_show_drafts' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_order',
    ],
    'ms3_order_redirect_thanks_id' => [
        'value' => 1,
        'xtype' => 'numberfield',
        'area' => 'ms3_order',
    ],
    'ms3_order_register_user_on_submit' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_order',
    ],
    'ms3_date_format' => [
        'value' => 'd.m.y H:M',
        'xtype' => 'textfield',
        'area' => 'ms3_order',
    ],
    'ms3_email_manager' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_order',
    ],
    'ms3_delete_drafts_after' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_order',
    ],
    'ms3_order_log_actions' => [
        'value' => 'status,products,field,address',
        'xtype' => 'textfield',
        'area' => 'ms3_order',
    ],

    'ms3_token_name' => [
        'value' => 'ms3_token',
        'xtype' => 'textfield',
        'area' => 'ms3_frontend',
    ],
    'ms3_register_global_config' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_frontend',
    ],
    'ms3_frontend_assets' => [
        'value' => '[
            "[[+cssUrl]]web\/lib\/izitoast\/iziToast.min.css",
            "[[+jsUrl]]web\/lib\/izitoast\/iziToast.js",
            "[[+jsUrl]]web\/modules\/hooks.js",
            "[[+jsUrl]]web\/modules\/message.js",
            "[[+jsUrl]]web\/core\/ApiClient.js",
            "[[+jsUrl]]web\/core\/TokenManager.js",
            "[[+jsUrl]]web\/core\/CartAPI.js",
            "[[+jsUrl]]web\/core\/OrderAPI.js",
            "[[+jsUrl]]web\/core\/CustomerAPI.js",
            "[[+jsUrl]]web\/ui\/CartUI.js",
            "[[+jsUrl]]web\/ui\/OrderUI.js",
            "[[+jsUrl]]web\/ui\/CustomerUI.js",
            "[[+jsUrl]]web\/ms3.js"
        ]',
        'xtype' => 'textarea',
        'area' => 'ms3_frontend',
    ],
//    'ms3_register_frontend' => [
//        'value' => true,
//        'xtype' => 'combo-boolean',
//        'area' => 'ms3_frontend',
//    ],
    'ms3_status_draft' => [
        'value' => 1,
        'xtype' => 'numberfield',
        'area' => 'ms3_statuses',
    ],
    'ms3_utility_import_fields' => [
        'value' => 'pagetitle,parent,price,article',
        'xtype' => 'textfield',
        'area' => 'ms3_import',
    ],
    'ms3_utility_import_fields_delimiter' => [
        'value' => ';',
        'xtype' => 'textfield',
        'area' => 'ms3_import',
    ],
    'ms3_import_sync_limit' => [
        'value' => 300,
        'xtype' => 'numberfield',
        'area' => 'ms3_import',
    ],
    'ms3_import_preview_rows' => [
        'value' => 5,
        'xtype' => 'numberfield',
        'area' => 'ms3_import',
    ],
    'ms3_status_new' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_statuses',
    ],
    'ms3_status_paid' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_statuses',
    ],
    'ms3_status_canceled' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_statuses',
    ],
    'ms3_status_for_stat' => [
        'value' => '2,3',
        'xtype' => 'textfield',
        'area' => 'ms3_statuses',
    ],
    'ms3_use_scheduler' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_main',
    ],
    'ms3_customer_grid_fields' => [
        'value' => 'id,first_name,last_name,email,phone',
        'xtype' => 'textarea',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_window_fields' => [
        'value' => 'id,first_name,last_name,email,phone',
        'xtype' => 'textarea',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_address_grid_fields' => [
        'value' => 'id,city,street,building',
        'xtype' => 'textarea',
        'area' => 'ms3_customers',
    ],

    // Customer Authentication & Registration
    'ms3_customer_auto_register_on_order' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_auto_login_on_order' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_require_email_verification' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_send_welcome_email' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_customers',
    ],

    // Customer Sync with modUser
    'ms3_customer_sync_enabled' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_sync_create_moduser' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_sync_delete_with_user' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_sync_user_group' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_duplicate_fields' => [
        'value' => '["email", "phone"]',
        'xtype' => 'textfield',
        'area' => 'ms3_customers',
    ],

    // Token Security Settings
    'ms3_customer_token_ttl' => [
        'value' => 86400, // 24 часа
        'xtype' => 'numberfield',
        'area' => 'ms3_security',
    ],
    'ms3_snippet_token_secret' => [
        'value' => '', // Генерируется автоматически при первом запуске
        'xtype' => 'textfield',
        'area' => 'ms3_security',
    ],
    'ms3_snippet_cache_ttl' => [
        'value' => 3600, // 1 час
        'xtype' => 'numberfield',
        'area' => 'ms3_security',
    ],

    // Login Security
    'ms3_customer_max_login_attempts' => [
        'value' => 5,
        'xtype' => 'numberfield',
        'area' => 'ms3_security',
    ],
    'ms3_customer_block_duration' => [
        'value' => 300, // 5 минут в секундах
        'xtype' => 'numberfield',
        'area' => 'ms3_security',
    ],

    // Password Requirements
    'ms3_password_min_length' => [
        'value' => 8,
        'xtype' => 'numberfield',
        'area' => 'ms3_security',
    ],
    'ms3_password_require_uppercase' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_security',
    ],
    'ms3_password_require_number' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_security',
    ],
    'ms3_password_require_special' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_security',
    ],

    // Currency and Formatting Settings
    'ms3_currency_symbol' => [
        'value' => '₽',
        'xtype' => 'textfield',
        'area' => 'ms3_product',
    ],
    'ms3_currency_position' => [
        'value' => 'after',
        'xtype' => 'textfield',
        'area' => 'ms3_product',
    ],
];
