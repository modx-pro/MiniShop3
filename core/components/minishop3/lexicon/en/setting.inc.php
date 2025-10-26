<?php

/**
 * Settings English Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

$_lang['area_ms3_main'] = 'Main settings';
$_lang['area_ms3_category'] = 'Product category';
$_lang['area_ms3_product'] = 'Product';
$_lang['area_ms3_gallery'] = 'Gallery';
$_lang['area_ms3_cart'] = 'Cart';
$_lang['area_ms3_order'] = 'Orders';
$_lang['area_ms3_frontend'] = 'Frontend';
$_lang['area_ms3_payment'] = 'Payments';
$_lang['area_ms3_import'] = 'Import';
$_lang['area_ms3_statuses'] = 'Statuses';
$_lang['area_ms3_customers'] = 'Customers';

$_lang['setting_ms3_services'] = 'Shop services';
$_lang['setting_ms3_services_desc'] = 'Array of registered classes for cart, order, delivery and payment. Used by third-party components to load their functionality.';
$_lang['setting_ms3_plugins'] = 'Shop plugins';
$_lang['setting_ms3_plugins_desc'] = 'Array of registered plugins for extending shop model objects: products, customer profiles, etc.';
$_lang['setting_ms3_chunks_categories'] = 'Categories for chunks list';
$_lang['setting_ms3_chunks_categories_desc'] = 'Comma-separated list of category IDs for chunks list.';
$_lang['setting_ms3_tmp_storage'] = 'Cart and temporary order fields storage';
$_lang['setting_ms3_tmp_storage_desc'] = "
To store cart and temporary order fields in session specify <strong>session</strong><br>
To store in database specify <strong>db</strong>";

$_lang['setting_ms3_category_grid_fields'] = 'Product table fields';
$_lang['setting_ms3_category_grid_fields_desc'] = 'Comma-separated list of visible fields in category products table. Available: "id,menuindex,pagetitle,article,price,thumb,new,favorite,popular';
$_lang['setting_ms3_product_main_fields'] = 'Product panel main fields';
$_lang['setting_ms3_product_main_fields_desc'] = 'Comma-separated list of product panel fields. For example: "pagetitle,longtitle,content".';
$_lang['setting_ms3_product_extra_fields'] = 'Product extra fields';
$_lang['setting_ms3_product_extra_fields_desc'] = 'Comma-separated list of additional product fields used in the shop. For example: "price,old_price,weight".';

$_lang['setting_mgr_tree_icon_mscategory'] = 'Category icon';
$_lang['setting_mgr_tree_icon_mscategory_desc'] = 'MiniShop3 product category icon in resources tree';
$_lang['setting_mgr_tree_icon_msproduct'] = 'Product icon';
$_lang['setting_mgr_tree_icon_msproduct_desc'] = 'MiniShop3 product icon in resources tree';

$_lang['setting_ms3_product_tab_extra'] = 'Product properties tab';
$_lang['setting_ms3_product_tab_extra_desc'] = 'Show product properties tab?';
$_lang['setting_ms3_product_tab_gallery'] = 'Product gallery tab';
$_lang['setting_ms3_product_tab_gallery_desc'] = 'Show product gallery tab?';
$_lang['setting_ms3_product_tab_links'] = 'Product links tab';
$_lang['setting_ms3_product_tab_links_desc'] = 'Show product links tab?';
$_lang['setting_ms3_product_tab_options'] = 'Product options tab';
$_lang['setting_ms3_product_tab_options_desc'] = 'Show product options tab?';
$_lang['setting_ms3_product_tab_categories'] = 'Product categories tab';
$_lang['setting_ms3_product_tab_categories_desc'] = 'Show product categories tab?';

$_lang['setting_ms3_category_show_comments'] = 'Show category comments';
$_lang['setting_ms3_category_show_comments_desc'] = 'Show comments left on all category products if "Tickets" component is installed';
$_lang['setting_ms3_category_show_nested_products'] = 'Show nested category products';
$_lang['setting_ms3_category_show_nested_products_desc'] = 'If you enable this option, all nested products will be shown in category. They are highlighted with different color and have their parent category name under pagetitle.';
$_lang['setting_ms3_category_show_options'] = 'Show category product options';
$_lang['setting_ms3_category_show_options_desc'] = 'Show options for category products.';
$_lang['setting_ms3_category_remember_tabs'] = 'Remember category tab';
$_lang['setting_ms3_category_remember_tabs_desc'] = 'If enabled, active category panel tab will be remembered and restored on page load.';
$_lang['setting_ms3_category_remember_grid'] = 'Remember category grid';
$_lang['setting_ms3_category_remember_grid_desc'] = 'If enabled, category grid state will be remembered and restored on page load, including page number and search string.';
$_lang['setting_ms3_category_id_as_alias'] = 'Category id as alias';
$_lang['setting_ms3_category_id_as_alias_desc'] = 'If enabled, aliases for category friendly URLs will not be generated. Their IDs will be used instead.';
$_lang['setting_ms3_category_content_default'] = 'Default category content';
$_lang['setting_ms3_category_content_default_desc'] = 'Here you can specify default content for newly created categories. By default it shows child products.';
$_lang['setting_ms3_product_show_comments'] = 'Show product comments';
$_lang['setting_ms3_product_show_comments_desc'] = 'Show comments left on product if "Tickets" component is installed';
$_lang['setting_ms3_template_category_default'] = 'Default template for new categories';
$_lang['setting_ms3_template_category_default_desc'] = 'Select template that will be set by default when creating a category.';
$_lang['setting_ms3_template_product_default'] = 'Default template for new products';
$_lang['setting_ms3_template_product_default_desc'] = 'Select template that will be set by default when creating a product.';
$_lang['setting_ms3_product_show_in_tree_default'] = 'Show in tree by default';
$_lang['setting_ms3_product_show_in_tree_default_desc'] = 'Enable this option to make all created products visible in resources tree.';
$_lang['setting_ms3_product_source_default'] = 'Default file source';
$_lang['setting_ms3_product_source_default_desc'] = 'Default file source for product image gallery.';
$_lang['setting_ms3_product_vertical_tabs'] = 'Vertical tabs on product page';
$_lang['setting_ms3_product_vertical_tabs_desc'] = 'How to display product page? Disabling this option allows fitting product page on screens with small horizontal resolution. Not recommended.';
$_lang['setting_ms3_product_remember_tabs'] = 'Remember product tab';
$_lang['setting_ms3_product_remember_tabs_desc'] = 'If enabled, active product panel tab will be remembered and restored on page load.';

$_lang['setting_ms3_product_thumbnail_size'] = 'Default thumbnail size';
$_lang['setting_ms3_product_thumbnail_size_desc'] = 'Here you can specify the size of pre-reduced image copy for product "thumb" field insertion. Of course, this size must also exist in media source settings to generate such thumbnails. Otherwise you will get MiniShop3 logo instead of product image in admin panel.';

$_lang['setting_ms3_product_thumbnail_default'] = 'Default thumbnail file';
$_lang['setting_ms3_product_thumbnail_default_desc'] = 'Here you can specify path to default thumbnail file for product "thumb" field insertion. By default you get MiniShop3 logo.';
$_lang['setting_ms3_product_id_as_alias'] = 'Product id as alias';
$_lang['setting_ms3_product_id_as_alias_desc'] = 'If enabled, aliases for product friendly URLs will not be generated. Their IDs will be used instead.';

$_lang['setting_ms3_cart_handler_class'] = 'Cart handler class';
$_lang['setting_ms3_cart_handler_class_desc'] = 'Name of the class that implements cart logic.';
$_lang['setting_ms3_cart_context'] = 'Use single cart for all contexts?';
$_lang['setting_ms3_cart_context_desc'] = 'If enabled, a common cart is used for all contexts. If disabled - each context uses its own cart.';
$_lang['setting_ms3_order_handler_class'] = 'Order handler class';
$_lang['setting_ms3_order_handler_class_desc'] = 'Name of the class that implements order checkout logic.';
$_lang['setting_ms3_cart_max_count'] = 'Maximum number of products in cart';
$_lang['setting_ms3_cart_max_count_desc'] = 'Default is 1000. When this value is exceeded, a notification will be displayed.';
$_lang['setting_ms3_order_tv_list'] = 'Comma-separated list of TVs that will be included in email';
$_lang['setting_ms3_order_tv_list_desc'] = 'Enter comma-separated list of product TVs to use them in email chunks';
$_lang['setting_ms3_order_user_groups'] = 'Customer registration groups';
$_lang['setting_ms3_order_user_groups_desc'] = 'Comma-separated list of groups to which you want to add new customers when placing an order.';
$_lang['setting_ms3_order_redirect_thanks_id'] = 'ID of "Thank you for your order" page';
$_lang['setting_ms3_order_redirect_thanks_id_desc'] = 'ID of the page to redirect to after placing an order.';
$_lang['setting_ms3_order_register_user_on_submit'] = 'Create system user when ordering';
$_lang['setting_ms3_order_register_user_on_submit_desc'] = 'By enabling this setting, you will create a new system user when ordering, who can be assigned rights and placed in a specific user group. Disabled by default.';
$_lang['setting_ms3_order_show_drafts'] = 'Show drafts in orders list';
$_lang['setting_ms3_order_show_drafts_desc'] = 'Select No if you do not want to see drafts in orders table.';
$_lang['setting_ms3_email_manager'] = 'Manager email addresses';
$_lang['setting_ms3_email_manager_desc'] = 'Comma-separated list of manager email addresses to send order status change notifications to.';
$_lang['setting_ms3_date_format'] = 'Date format';
$_lang['setting_ms3_date_format_desc'] = 'Specify MiniShop3 date format using php date() function syntax. Default format is "d.m.y H:M".';
$_lang['setting_ms3_price_format'] = 'Price format';
$_lang['setting_ms3_price_format_desc'] = 'Specify how to format product prices with number_format() function. Use JSON string with array to pass 3 parameters: decimals count, decimal separator and thousands separator. Default format is [2,"."," "], which converts "15336.6" to "15 336.60"';
$_lang['setting_ms3_price_format_no_zeros'] = 'Remove trailing zeros in prices';
$_lang['setting_ms3_price_format_no_zeros_desc'] = 'By default, product prices are displayed with two decimals: "15.20". If this option is enabled, trailing zeros in price are removed and you get "15.2".';
$_lang['setting_ms3_weight_format'] = 'Weight format';
$_lang['setting_ms3_weight_format_desc'] = 'Specify how to format product weight with number_format() function. Use JSON string with array to pass 3 parameters: decimals count, decimal separator and thousands separator. Default format is [3,"."," "], which converts "141.3" to "141.300"';
$_lang['setting_ms3_weight_format_no_zeros'] = 'Remove trailing zeros in weight';
$_lang['setting_ms3_weight_format_no_zeros_desc'] = 'By default, product weight is displayed with three decimals: "15.250". If this option is enabled, trailing zeros in weight are removed and you get "15.25".';
$_lang['setting_ms3_price_snippet'] = 'Price modifier';
$_lang['setting_ms3_price_snippet_desc'] = 'Here you can specify snippet name to modify price when displaying on site and adding to cart. It should accept "$product" object and return a number.';
$_lang['setting_ms3_weight_snippet'] = 'Weight modifier';
$_lang['setting_ms3_weight_snippet_desc'] = 'Here you can specify snippet name to modify product weight when displaying on site and adding to cart. It should accept "$product" object and return a number.';
$_lang['setting_ms3_token_name'] = 'Token name';
$_lang['setting_ms3_token_name_desc'] = 'Token name used to identify visitor. Default is <strong>ms3_token</strong>';
$_lang['setting_ms3_register_global_config'] = 'Register global settings config in DOM';
$_lang['setting_ms3_register_global_config_desc'] = 'Registers json array of important settings in DOM for use by scripts';
$_lang['setting_ms3_frontend_assets'] = 'List of CSS/JS files to include';
$_lang['setting_ms3_frontend_assets_desc'] = 'CSS files will be included in head, JS files will be included at end of html with defer attribute';


$_lang['setting_ms3_order_format_num'] = 'Order numbering format';
$_lang['setting_ms3_order_format_num_desc'] = 'Order numbering format. Available values in PHP date() format';
$_lang['setting_ms3_order_format_num_separator'] = 'Order numbering separator';
$_lang['setting_ms3_order_format_num_separator_desc'] = 'Order numbering separator. Available values: "/", "," and "-"';
$_lang['setting_ms3_order_grid_fields'] = 'Orders table fields';
$_lang['setting_ms3_order_grid_fields_desc'] = 'List of fields to be shown in orders table. Available: "createdon,updatedon,num,cost,cart_cost,delivery_cost,weight,status,delivery,payment,customer,first_name,last_name".';
$_lang['setting_ms3_order_address_fields'] = 'Delivery address fields';
$_lang['setting_ms3_order_address_fields_desc'] = 'List of delivery fields to be shown on third tab of order card. Available: "first_name,last_name,phone,index,country,region,metro,building,city,street,room,entrance,floor,text_address". If parameter is empty, tab will be hidden.';
$_lang['setting_ms3_order_product_fields'] = 'Purchases table fields';
$_lang['setting_ms3_order_product_fields_desc'] = 'List of ordered products table fields. Available: "count,price,weight,cost,options". Product fields are specified with "product_" prefix, for example "product_pagetitle,product_article". Additionally, you can specify values from options field with "option_" prefix, for example: "option_color,option_size".';
$_lang['setting_ms3_order_product_options'] = 'Product options fields in order';
$_lang['setting_ms3_order_product_options_desc'] = 'List of editable product options in order window. Default is color, size';
$_lang['setting_ms3_delete_drafts_after'] = 'Delete order drafts after';
$_lang['setting_ms3_delete_drafts_after_desc'] = 'Specify strtotime()-compatible string (e.g. "-1 year" or "-2 weeks") to automatically delete outdated order drafts older than specified time period. Cleanup runs automatically every 30 minutes';
$_lang['setting_ms3_status_draft'] = 'Draft order status ID';
$_lang['setting_ms3_status_draft_desc'] = 'What status to set for draft order';
$_lang['setting_ms3_status_new'] = 'Initial order status ID';
$_lang['setting_ms3_status_new_desc'] = 'What status to set for new placed order';
$_lang['setting_ms3_status_paid'] = 'Paid order status ID';
$_lang['setting_ms3_status_paid_desc'] = 'What status to set after order payment';
$_lang['setting_ms3_status_canceled'] = 'Canceled order status ID';
$_lang['setting_ms3_status_canceled_desc'] = 'What status to set when canceling order';
$_lang['setting_ms3_status_for_stat'] = 'Status IDs for statistics';
$_lang['setting_ms3_status_for_stat_desc'] = 'Comma-separated statuses for building COMPLETED orders statistics';
$_lang['setting_ms3_use_scheduler'] = 'Use queue manager';
$_lang['setting_ms3_use_scheduler_desc'] = 'Before using, make sure you have Scheduler component installed';
$_lang['setting_ms3_utility_import_fields'] = 'Import fields list';
$_lang['setting_ms3_utility_import_fields_delimiter'] = 'Import file columns delimiter';

$_lang['setting_ms3_customer_grid_fields'] = 'Customers table fields';
$_lang['setting_ms3_customer_grid_fields_desc'] = 'List of fields to be shown in customers table. Available: "id,first_name,last_name,email,phone".';
$_lang['setting_ms3_customer_window_fields'] = 'Editable fields in customer management window';
$_lang['setting_ms3_customer_window_fields_desc'] = 'Editable fields in customer management window. Available: "id,first_name,last_name,email,phone".';
$_lang['setting_ms3_customer_address_grid_fields'] = 'Customer address table fields';
$_lang['setting_ms3_customer_address_grid_fields_desc'] = 'Editable fields in customer address management window. Available: "id,country,region.index,metro,city,street,building,entrance,floor,room,comment".';


$_lang['ms3_source_thumbnails_desc'] = 'JSON-encoded array with parameters for generating image thumbnails.';
$_lang['ms3_source_maxUploadWidth_desc'] = 'Maximum width of image for upload. Anything larger will be reduced to this value.';
$_lang['ms3_source_maxUploadHeight_desc'] = 'Maximum height of image for upload. Anything larger will be reduced to this value.';
$_lang['ms3_source_maxUploadSize_desc'] = 'Maximum size of uploaded images (in bytes).';
$_lang['ms3_source_imageNameType_desc'] = 'This parameter specifies how to rename file on upload. Hash - generates unique name depending on file content. Friendly - generates name using site friendly URL algorithm (controlled by system settings).';
$_lang['setting_ms3_utility_import_fields'] = 'Import fields list';
$_lang['setting_ms3_utility_import_fields_delimiter'] = 'Import file columns delimiter';

// Token Security Settings
$_lang['area_ms3_security'] = 'Security';
$_lang['setting_ms3_customer_token_ttl'] = 'Customer token Time-To-Live (TTL)';
$_lang['setting_ms3_customer_token_ttl_desc'] = 'Time in seconds for which the customer token remains valid. Default is 86400 (24 hours). After expiration, the user will receive a new token.';
$_lang['setting_ms3_snippet_token_secret'] = 'Secret key for snippet tokens';
$_lang['setting_ms3_snippet_token_secret_desc'] = 'Cryptographically secure secret key for generating snippet tokens. Generated automatically on first run. DO NOT change this value unless necessary!';
$_lang['setting_ms3_snippet_cache_ttl'] = 'Snippet data cache Time-To-Live (TTL)';
$_lang['setting_ms3_snippet_cache_ttl_desc'] = 'Time in seconds for which snippet parameters are stored in cache. Default is 3600 (1 hour). Used for cart performance optimization.';

// Currency and Formatting Settings
$_lang['setting_ms3_currency_symbol'] = 'Currency symbol';
$_lang['setting_ms3_currency_symbol_desc'] = 'Currency symbol for price display. Default is "₽" (ruble). Examples: $, €, £, ₽, ₴, ¥, ₸.';
$_lang['setting_ms3_currency_position'] = 'Currency symbol position';
$_lang['setting_ms3_currency_position_desc'] = 'Where to display currency symbol relative to price. Valid values: "before" (before price: $ 100) or "after" (after price: 100 ₽). Default is "after".';

