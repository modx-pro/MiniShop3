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
        'value' => '{"cart":["MiniShop3\\\\Controllers\\\\Cart\\\\Cart"],"order":["MiniShop3\\\\Controllers\\\\Order\\\\Order"],"payment":["MiniShop3\\\\Controllers\\\\Payment\\\\Payment"],"delivery":["MiniShop3\\\\Controllers\\\\Delivery\\\\Delivery"]}',
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
            "[[+jsUrl]]web\/ms3.js",
            "[[+jsUrl]]web\/modules\/form.js",
            "[[+jsUrl]]web\/modules\/request.js", 
            "[[+jsUrl]]web\/modules\/callback.js", 
            "[[+jsUrl]]web\/modules\/cart.js",
            "[[+jsUrl]]web\/modules\/customer.js",
            "[[+jsUrl]]web\/modules\/order.js"
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
];
