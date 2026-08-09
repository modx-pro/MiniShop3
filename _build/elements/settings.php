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

    'ms3_chunks_categories' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_main',
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
    'ms3_category_id_as_alias' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_category',
    ],
    'ms3_template_category_default' => [
        'value' => '',
        'xtype' => 'modx-combo-template',
        'area' => 'ms3_category',
    ],
    'ms3_product_main_fields' => [
        'value' => 'pagetitle,longtitle,description,introtext,content',
        'xtype' => 'textarea',
        'area' => 'ms3_product',
    ],
    'ms3_product_extra_fields' => [
        'value' => 'price,old_price,article,weight,color,size,vendor_id,made_in,tags,new,popular,favorite',
        'xtype' => 'textarea',
        'area' => 'ms3_product',
    ],
    'ms3_price_snippet' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_product',
    ],
    'ms3_weight_snippet' => [
        'value' => '',
        'xtype' => 'textfield',
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
    'ms3_cart_page_id' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_cart',
    ],
    'ms3_order_page_id' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_order',
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
    'ms3_order_user_groups' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_order',
    ],
    'ms3_order_show_drafts' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_order',
    ],
    'ms3_order_redirect_thanks_id' => [
        'value' => 1,
        'xtype' => 'numberfield',
        'area' => 'ms3_order',
    ],
    'ms3_order_success_page_id' => [
        'value' => 0,
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
            "[[+jsUrl]]web\/modules\/confirm.js",
            "[[+jsUrl]]web\/core\/Selectors.js",
            "[[+jsUrl]]web\/core\/ApiClient.js",
            "[[+jsUrl]]web\/core\/TokenManager.js",
            "[[+jsUrl]]web\/core\/CartAPI.js",
            "[[+jsUrl]]web\/core\/OrderAPI.js",
            "[[+jsUrl]]web\/core\/CustomerAPI.js",
            "[[+jsUrl]]web\/ui\/CartUI.js",
            "[[+jsUrl]]web\/ui\/OrderUI.js",
            "[[+jsUrl]]web\/ui\/CustomerUI.js",
            "[[+jsUrl]]web\/ui\/QuantityUI.js",
            "[[+jsUrl]]web\/ui\/ProductCardUI.js",
            "[[+jsUrl]]web\/ui\/AuthUI.js",
            "[[+jsUrl]]web\/ms3.js"
        ]',
        'xtype' => 'textarea',
        'area' => 'ms3_frontend',
    ],
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
    'ms3_import_upload_path' => [
        'value' => 'assets/import/',
        'xtype' => 'textfield',
        'area' => 'ms3_import',
    ],
    'ms3_status_new' => [
        'value' => 2,
        'xtype' => 'numberfield',
        'area' => 'ms3_statuses',
    ],
    'ms3_status_paid' => [
        'value' => 3,
        'xtype' => 'numberfield',
        'area' => 'ms3_statuses',
    ],
    'ms3_status_canceled' => [
        'value' => 5,
        'xtype' => 'numberfield',
        'area' => 'ms3_statuses',
    ],
    'ms3_customer_cancel_allowed_statuses' => [
        'value' => '2,3',
        'xtype' => 'textfield',
        'area' => 'ms3_customers',
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

    // Customer Pages
    'ms3_customer_login_page_id' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_register_page_id' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_profile_page_id' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_addresses_page_id' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_orders_page_id' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_redirect_after_login' => [
        'value' => 0,
        'xtype' => 'numberfield',
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
    'ms3_customer_require_privacy_consent' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_customers',
    ],
    'ms3_customer_auto_login_after_register' => [
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
    'ms3_customer_api_token_ttl' => [
        'value' => 86400, // 24 часа
        'xtype' => 'numberfield',
        'area' => 'ms3_security',
    ],
    'ms3_password_reset_token_ttl' => [
        'value' => 3600, // 1 час
        'xtype' => 'numberfield',
        'area' => 'ms3_security',
    ],
    'ms3_email_verification_token_ttl' => [
        'value' => 86400, // 24 часа
        'xtype' => 'numberfield',
        'area' => 'ms3_security',
    ],
    'ms3_email_verification_url' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_security',
    ],
    'ms3_email_verification_success_url' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_security',
    ],
    'ms3_payment_secret' => [
        'value' => '',
        'xtype' => 'textfield',
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
        // Must match Format::DEFAULT_CURRENCY_SYMBOL (U+20BD); escape is ASCII-safe for transport builds
        'value' => "\u{20BD}",
        'xtype' => 'textfield',
        'area' => 'ms3_product',
    ],
    'ms3_currency_position' => [
        'value' => 'after',
        'xtype' => 'textfield',
        'area' => 'ms3_product',
    ],
    'ms3_weight_unit' => [
        'value' => 'kg',
        'xtype' => 'textfield',
        'area' => 'ms3_product',
    ],

    // API Settings
    'ms3_api_debug' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms3_api',
    ],
    'ms3_cors_allowed_origins' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_api',
    ],
    'ms3_rate_limit_max_attempts' => [
        'value' => 60,
        'xtype' => 'numberfield',
        'area' => 'ms3_api',
    ],
    'ms3_rate_limit_decay_seconds' => [
        'value' => 60,
        'xtype' => 'numberfield',
        'area' => 'ms3_api',
    ],
    'ms3_rate_limit_store' => [
        'value' => 'file',
        'xtype' => 'textfield',
        'area' => 'ms3_api',
    ],
    'ms3_rate_limit_storage_path' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_api',
    ],
    'ms3_rate_limit_redis_dsn' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_api',
    ],
    'ms3_rate_limit_redis_host' => [
        'value' => '127.0.0.1',
        'xtype' => 'textfield',
        'area' => 'ms3_api',
    ],
    'ms3_rate_limit_redis_port' => [
        'value' => 6379,
        'xtype' => 'numberfield',
        'area' => 'ms3_api',
    ],
    'ms3_rate_limit_redis_password' => [
        'value' => '',
        'xtype' => 'text-password',
        'area' => 'ms3_api',
    ],
    'ms3_rate_limit_redis_database' => [
        'value' => 0,
        'xtype' => 'numberfield',
        'area' => 'ms3_api',
    ],
    'ms3_rate_limit_memcached_servers' => [
        'value' => '127.0.0.1:11211',
        'xtype' => 'textfield',
        'area' => 'ms3_api',
    ],

    // Notifications
    'ms3_telegram_bot_token' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_notifications',
    ],
    'ms3_telegram_manager' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms3_notifications',
    ],
];
