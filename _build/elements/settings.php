<?php

return [
    'mgr_tree_icon_mscategory' => [
        'value' => 'icon icon-barcode',
        'xtype' => 'textarea',
        'area' => 'ms_category',
        'key' => 'mgr_tree_icon_mscategory',
    ],
    'mgr_tree_icon_msproduct' => [
        'value' => 'icon icon-tag',
        'xtype' => 'textarea',
        'area' => 'ms_product',
        'key' => 'mgr_tree_icon_msproduct',
    ],

    'ms_add_icon_category' => [
        'value' => 'icon icon-folder-open',
        'xtype' => 'textfield',
        'area' => 'ms_category',
    ],
    'ms_add_icon_product' => [
        'value' => 'icon icon-tag',
        'xtype' => 'textfield',
        'area' => 'ms_category',
    ],

    'ms_services' => [
        'value' => '{"cart":[],"order":[],"payment":[],"delivery":[]}',
        'xtype' => 'textarea',
        'area' => 'ms_main',
    ],
    'ms_plugins' => [
        'value' => '[]',
        'xtype' => 'textarea',
        'area' => 'ms_main',
    ],
    'ms_chunks_categories' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms_main',
    ],
    'ms_tmp_storage' => [
        'value' => 'session',
        'xtype' => 'textfield',
        'area' => 'ms_main',
    ],

    'ms_category_grid_fields' => [
        'value' => 'id,menuindex,pagetitle,article,price,thumb,new,favorite,popular',
        'xtype' => 'textarea',
        'area' => 'ms_category',
    ],
    'ms_category_show_nested_products' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_category',
    ],
    'ms_category_show_comments' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_category',
    ],
    'ms_category_show_options' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms_category',
    ],
    'ms_category_remember_tabs' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_category',
    ],
    'ms_category_id_as_alias' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms_category',
    ],
    'ms_category_content_default' => [
        'value' => '',
        'xtype' => 'textarea',
        'area' => 'ms_category',
    ],
    'ms_template_category_default' => [
        'value' => '',
        'xtype' => 'modx-combo-template',
        'area' => 'ms_category',
    ],
    'ms_product_extra_fields' => [
        'value' => 'price,old_price,article,weight,color,size,vendor,made_in,tags,new,popular,favorite',
        'xtype' => 'textarea',
        'area' => 'ms_product',
    ],
    'ms_product_show_comments' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],
    'ms_template_product_default' => [
        'value' => '',
        'xtype' => 'modx-combo-template',
        'area' => 'ms_product',
    ],
    'ms_product_show_in_tree_default' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],
    'ms_product_source_default' => [
        'value' => 0,
        'xtype' => 'modx-combo-source',
        'area' => 'ms_product',
    ],
    'ms_product_thumbnail_default' => [
        'value' => '{assets_url}components/minishop3/img/mgr/ms3_thumb.png',
        'xtype' => 'textfield',
        'area' => 'ms_product',
    ],
    'ms_product_thumbnail_size' => [
        'value' => 'small',
        'xtype' => 'textfield',
        'area' => 'ms_product',
    ],
    'ms_product_remember_tabs' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],
    'ms_product_id_as_alias' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],
    'ms_price_format' => [
        'value' => '[2, ".", " "]',
        'xtype' => 'textfield',
        'area' => 'ms_product',
    ],
    'ms_weight_format' => [
        'value' => '[3, ".", " "]',
        'xtype' => 'textfield',
        'area' => 'ms_product',
    ],
    'ms_price_format_no_zeros' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],
    'ms_weight_format_no_zeros' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],
    'ms_product_tab_extra' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],
    'ms_product_tab_gallery' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],
    'ms_product_tab_links' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],
    'ms_product_tab_options' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],
    'ms_product_tab_categories' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_product',
    ],

    'ms_cart_handler_class' => [
        'value' => 'msCartHandler',
        'xtype' => 'textfield',
        'area' => 'ms_cart',
    ],
    'ms_cart_context' => [
        'value' => '',
        'xtype' => 'combo-boolean',
        'area' => 'ms_cart',
    ],
    'ms_cart_max_count' => [
        'value' => 1000,
        'xtype' => 'numberfield',
        'area' => 'ms_cart',
    ],

    'ms_order_format_num' => [
        'value' => '%y%m',
        'xtype' => 'textfield',
        'area' => 'ms_order',
    ],
    'ms_order_format_num_separator' => [
        'value' => '/',
        'xtype' => 'textfield',
        'area' => 'ms_order',
    ],
    'ms_order_grid_fields' => [
        'value' => 'id,num,customer,status,cost,weight,delivery,payment,createdon,updatedon,comment',
        'xtype' => 'textarea',
        'area' => 'ms_order',
    ],
    'ms_order_address_fields' => [
        'xtype' => 'textarea',
        'value' => 'receiver,phone,index,country,region,city,metro,street,building,entrance,floor,room,comment,text_address',
        'area' => 'ms_order',
    ],
    'ms_order_product_fields' => [
        'xtype' => 'textarea',
        'value' => 'product_pagetitle,vendor_name,product_article,weight,price,count,cost',
        'area' => 'ms_order',
    ],
    'ms_order_product_options' => [
        'xtype' => 'textarea',
        'value' => 'size,color',
        'area' => 'ms_order',
    ],

    'ms_order_handler_class' => [
        'value' => 'msOrderHandler',
        'xtype' => 'textfield',
        'area' => 'ms_order',
    ],
    'ms_order_user_groups' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms_order',
    ],
    'ms_date_format' => [
        'value' => '%d.%m.%y <span class="gray">%H:%M</span>',
        'xtype' => 'textfield',
        'area' => 'ms_order',
    ],
    'ms_email_manager' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms_order',
    ],

    'ms_frontend_css' => [
        'value' => '[[+cssUrl]]web/default.css',
        'xtype' => 'textfield',
        'area' => 'ms_frontend',
    ],
    'ms_frontend_message_css' => [
        'value' => '[[+cssUrl]]web/lib/jquery.jgrowl.min.css',
        'xtype' => 'textfield',
        'area' => 'ms_frontend',
    ],
    'ms_frontend_js' => [
        'value' => '[[+jsUrl]]web/default.js',
        'xtype' => 'textfield',
        'area' => 'ms_frontend',
    ],
    'ms_frontend_message_js' => [
        'value' => '[[+jsUrl]]web/lib/jquery.jgrowl.min.js',
        'xtype' => 'textfield',
        'area' => 'ms_frontend',
    ],
    'ms_frontend_message_js_settings' => [
        'value' => '[[+jsUrl]]web/message_settings.js',
        'xtype' => 'textfield',
        'area' => 'ms_frontend',
    ],
    'ms_register_frontend' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms_frontend',
    ],
];
