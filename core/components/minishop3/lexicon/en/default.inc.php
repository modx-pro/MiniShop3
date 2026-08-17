<?php

/**
 * Default English Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

include_once('setting.inc.php');
$files = scandir(dirname(__FILE__));
foreach ($files as $file) {
    if (str_starts_with($file, 'msp.')) {
        @include_once($file);
    }
}

$_lang['ms3_header'] = 'MiniShop3';
$_lang['ms3_menu_desc'] = 'Advanced online store';
$_lang['ms3_order'] = 'Order';
$_lang['ms3_orders'] = 'Orders';
$_lang['ms3_orders_intro'] = 'Order management panel. You can select multiple orders using Shift or Ctrl(Cmd).';
$_lang['ms3_orders_desc'] = 'Order management';
$_lang['ms3_customers'] = 'Customers';
$_lang['ms3_customers_desc'] = 'Customer management';
$_lang['ms3_settings'] = 'Settings';
$_lang['ms3_settings_intro'] = 'Shop settings management panel. Here you can specify payment methods, delivery methods and order statuses.';
$_lang['ms3_settings_desc'] = 'Order statuses, payment and delivery parameters';
$_lang['ms3_system_settings'] = 'System settings';
$_lang['ms3_system_settings_desc'] = 'MiniShop3 system settings';
$_lang['ms3_utilities'] = 'Utilities';
$_lang['ms3_utilities_desc'] = 'Developer tools';
$_lang['ms3_grid_fields_config_desc'] = 'Table Fields';
$_lang['ms3_payment'] = 'Payment';
$_lang['ms3_payment_cash'] = 'Cash';
$_lang['ms3_payments'] = 'Payment methods';
$_lang['ms3_payments_intro'] = 'You can create any payment methods for orders. Payment logic (sending customer to remote service, receiving payment, etc.) is implemented in the class you specify.<br/>For payment methods the "class" parameter is required.';
$_lang['ms3_delivery'] = 'Delivery';
$_lang['ms3_delivery_self_pickup'] = 'Self-pickup';
$_lang['ms3_email_link_to_order'] = 'Order in control panel →';
$_lang['ms3_deliveries'] = 'Delivery options';
$_lang['ms3_deliveries_intro'] = 'Possible delivery options. Logic for calculating delivery cost depending on distance and weight is implemented by the class you specify in settings.<br/>If you don\'t specify your own class, calculations will be performed by default algorithm.';
$_lang['ms3_statuses'] = 'Order statuses';
$_lang['ms3_statuses_intro'] = 'There are several required order statuses: "new", "paid", "sent" and "cancelled". They can be configured but cannot be deleted as they are necessary for shop operation. You can specify your own statuses for extended order workflow logic.<br/>Status can be final, meaning it cannot be switched to another, for example "sent" and "cancelled". Status can be fixed, meaning you cannot switch from it to earlier statuses, for example "paid" cannot be switched to "new".';
$_lang['ms3_vendors'] = 'Product vendors';
$_lang['ms3_vendors_intro'] = 'List of possible product vendors. What you add here can be selected in product "vendor" field.';
$_lang['ms3_link'] = 'Product link';
$_lang['ms3_links'] = 'Product links';
$_lang['ms3_links_intro'] = 'List of possible product links to each other. Link type characterizes how exactly it will work, it cannot be created, only selected from list.';
$_lang['ms3_option'] = 'Product option';
$_lang['ms3_options'] = 'Product options';
$_lang['ms3_options_intro'] = 'List of possible product options. Category tree is used to filter options of selected categories.<br/>To assign multiple options to categories at once, select them via Ctrl(Cmd) or Shift.';
$_lang['ms3_options_category_intro'] = 'List of possible product options in this category.';
$_lang['ms3_default_value'] = 'Default value';
$_lang['ms3_customer'] = 'Customer';
$_lang['ms3_all'] = 'All';
$_lang['ms3_type'] = 'Type';

$_lang['ms3_btn_create'] = 'Create';
$_lang['ms3_btn_copy'] = 'Copy';
$_lang['ms3_btn_save'] = 'Save';
$_lang['ms3_btn_edit'] = 'Edit';
$_lang['ms3_btn_view'] = 'View';
$_lang['ms3_btn_delete'] = 'Delete';
$_lang['ms3_btn_undelete'] = 'Restore';
$_lang['ms3_btn_publish'] = 'Enable';
$_lang['ms3_btn_unpublish'] = 'Disable';
$_lang['ms3_btn_cancel'] = 'Cancel';
$_lang['ms3_btn_back'] = 'Back (alt + &uarr;)';
$_lang['ms3_btn_prev'] = 'Previous product (alt + &larr;)';
$_lang['ms3_btn_next'] = 'Next product (alt + &rarr;)';
$_lang['ms3_btn_help'] = 'Help';
$_lang['ms3_btn_duplicate'] = 'Duplicate product';
$_lang['ms3_btn_addoption'] = 'Add';
$_lang['ms3_btn_assign'] = 'Assign';

$_lang['ms3_actions'] = 'Actions';
$_lang['ms3_search'] = 'Search';
$_lang['ms3_search_clear'] = 'Clear';

$_lang['ms3_category'] = 'Product category';
$_lang['ms3_category_tree'] = 'Category tree';
$_lang['ms3_category_type'] = 'Product category';
$_lang['ms3_category_create'] = 'Add category';
$_lang['ms3_category_create_here'] = 'Category with products';
$_lang['ms3_category_manage'] = 'Product management';
$_lang['ms3_category_duplicate'] = 'Copy category';
$_lang['ms3_category_publish'] = 'Publish category';
$_lang['ms3_category_unpublish'] = 'Unpublish';
$_lang['ms3_category_delete'] = 'Delete category';
$_lang['ms3_category_undelete'] = 'Restore category';
$_lang['ms3_category_view'] = 'View on site';
$_lang['ms3_category_new'] = 'New category';
$_lang['ms3_category_option_add'] = 'Add option';
$_lang['ms3_category_option_rank'] = 'Sort order';
$_lang['ms3_category_show_nested'] = 'Show nested products';

$_lang['ms3_product'] = 'Shop product';
$_lang['ms3_product_type'] = 'Shop product';
$_lang['ms3_product_create_here'] = 'Shop product';
$_lang['ms3_product_create'] = 'Add product';

$_lang['ms3_option_type'] = 'Option type';

$_lang['ms3_frontend_currency'] = '$';
$_lang['ms3_frontend_weight_unit'] = 'kg';
$_lang['ms3_frontend_count_unit'] = 'pcs';
$_lang['ms3_frontend_add_to_cart'] = 'Add to cart';
$_lang['ms3_frontend_tags'] = 'Tags';
$_lang['ms3_frontend_colors'] = 'Colors';
$_lang['ms3_frontend_color'] = 'Color';
$_lang['ms3_frontend_sizes'] = 'Sizes';
$_lang['ms3_frontend_size'] = 'Size';
$_lang['ms3_frontend_popular'] = 'Popular product';
$_lang['ms3_frontend_favorite'] = 'Recommended';
$_lang['ms3_frontend_new'] = 'New';
$_lang['ms3_frontend_deliveries'] = 'Delivery options';
$_lang['ms3_frontend_delivery'] = 'Delivery';
$_lang['ms3_frontend_payments'] = 'Payment methods';
$_lang['ms3_frontend_payment'] = 'Payment';
$_lang['ms3_frontend_delivery_select'] = 'Select delivery';
$_lang['ms3_frontend_payment_select'] = 'Select payment';
$_lang['ms3_frontend_credentials'] = 'Recipient data';
$_lang['ms3_frontend_address'] = 'Delivery address';
$_lang['ms3_frontend_customer'] = 'Customer';

$_lang['ms3_frontend_comment'] = 'Comment';
$_lang['ms3_frontend_first_name'] = 'First name';
$_lang['ms3_frontend_last_name'] = 'Last name';
$_lang['ms3_frontend_email'] = 'Email';
$_lang['ms3_frontend_phone'] = 'Phone';
$_lang['ms3_frontend_index'] = 'Postal code';
$_lang['ms3_frontend_country'] = 'Country';
$_lang['ms3_frontend_region'] = 'Region';
$_lang['ms3_frontend_city'] = 'City';
$_lang['ms3_frontend_street'] = 'Street';
$_lang['ms3_frontend_building'] = 'Building';
$_lang['ms3_frontend_room'] = 'Apt.';
$_lang['ms3_frontend_entrance'] = 'Entrance';
$_lang['ms3_frontend_floor'] = 'Floor';
$_lang['ms3_frontend_text_address'] = 'Address in one line';
$_lang['ms3_frontend_saved_addresses'] = 'Saved addresses';
$_lang['ms3_frontend_address_new'] = 'Enter new address';
$_lang['ms3_frontend_saved_addresses_help'] = 'Select from previously saved addresses or enter a new one';
$_lang['ms3_frontend_save_address'] = 'Save this address for future orders';
$_lang['ms3_frontend_save_address_help'] = 'The address will be available when placing future orders';

$_lang['ms3_frontend_order_cost'] = 'Total, with delivery';
$_lang['ms3_frontend_order_submit'] = 'Place order';
$_lang['ms3_frontend_save'] = 'Save';
$_lang['ms3_frontend_order_cancel'] = 'Clear form';
$_lang['ms3_frontend_order_success'] = 'Thank you for placing order <b>#[[+num]]</b> on our site <b>[[++site_name]]</b>!';

$_lang['ms3_frontend_article'] = 'Article';
$_lang['ms3_frontend_cart_total'] = 'Products total';
$_lang['ms3_frontend_total'] = 'Total to pay';
$_lang['ms3_frontend_delivery_address'] = 'Delivery address';
$_lang['ms3_frontend_delivery_method'] = 'Delivery method';
$_lang['ms3_frontend_payment_method'] = 'Payment method';
$_lang['ms3_frontend_go_to_cart'] = 'Go to cart';
$_lang['ms3_frontend_continue_shopping'] = 'Continue shopping';
$_lang['ms3_frontend_checkout'] = 'Checkout';
$_lang['ms3_frontend_cart_empty'] = 'Your cart is empty';
$_lang['ms3_frontend_cart_empty_desc'] = 'Add products to your cart to place an order';
$_lang['ms3_frontend_go_to_catalog'] = 'Go to catalog';

$_lang['ms3_message_close_all'] = 'close all';
$_lang['ms3_err_unknown'] = 'Unknown error';
$_lang['ms3_err_ns'] = 'This field is required';
$_lang['ms3_err_product_key_required'] = 'Product key is required';
$_lang['ms3_err_field_key_required'] = 'Field key is required';
$_lang['ms3_err_fields_required'] = 'Fields array is required';
$_lang['ms3_err_field_nf'] = 'Field not found';
$_lang['ms3_err_ae'] = 'This field must be unique';
$_lang['ms3_err_json'] = 'This field requires JSON string';
$_lang['ms3_repeater_validation_error'] = 'Repeater field "[[+field]]": [[+error]]';
$_lang['ms3_key_value_validation_error'] = 'Key-value field "[[+field]]": [[+error]]';

$_lang['ms3_err_user_nf'] = 'User not found.';
$_lang['ms3_err_product_nf'] = 'Product not found.';
$_lang['ms3_err_product_id_ns'] = 'Product ID is required.';
$_lang['ms3_err_order_nf'] = 'Order with this identifier not found.';
$_lang['ms3_err_order_load'] = 'Error loading order.';
$_lang['ms3_err_order_num_lock'] = 'Could not acquire a lock to generate the order number. Please try again.';
$_lang['ms3_err_order_num_save'] = 'Could not save the order number. Please try again.';
$_lang['ms3_err_product_not_in_category_scope'] = 'Product is not in the scope of this category.';
$_lang['ms3_err_status_nf'] = 'Status with this identifier not found.';
$_lang['ms3_err_delivery_nf'] = 'Delivery method with this identifier not found.';
$_lang['ms3_err_delivery_id_required'] = 'Delivery ID is required';
$_lang['ms3_err_payment_nf'] = 'Payment method with this identifier not found.';
$_lang['ms3_err_payment_id_required'] = 'Payment ID is required';
$_lang['ms3_err_status_final'] = 'Final status is set. It cannot be changed.';
$_lang['ms3_err_status_fixed'] = 'Fixed status is set. You cannot change it to earlier one.';
$_lang['ms3_err_status_wrong'] = 'Invalid order status.';
$_lang['ms3_err_status_same'] = 'This status is already set.';
$_lang['ms3_err_register_globals'] = 'Error: php parameter <b>register_globals</b> must be disabled.';
$_lang['ms3_err_link_equal'] = 'You are trying to add product link to itself';
$_lang['ms3_err_no_link'] = 'Link type not found';
$_lang['ms3_err_link_save'] = 'Could not save product link (see system log).';
$_lang['ms3_err_link_not_in_product_scope'] = 'This link does not belong to the current product';
$_lang['ms3_err_link_batch_not_supported'] = 'Batch link removal is not supported';
$_lang['ms3_err_value_duplicate'] = 'You did not enter value or entered duplicate.';

$_lang['ms3_err_gallery_save'] = 'Cannot save file (see system log).';
$_lang['ms3_err_gallery_ns'] = 'Empty file passed';
$_lang['ms3_err_gallery_ext'] = 'Invalid file extension';
$_lang['ms3_err_gallery_exists'] = 'Such image already exists in product gallery.';
$_lang['ms3_err_gallery_thumb'] = 'Failed to generate thumbnails. See system log.';
$_lang['ms3_err_gallery_upload'] = 'Cannot upload file.';
$_lang['ms3_err_wrong_image'] = 'File is not a valid image.';
$_lang['ms3_err_gallery_is_not_msproduct'] = '[msGallery] Resource with id = [[+id]] is not a product.';
$_lang['ms3_err_options_is_not_msproduct'] = '[msOptions] Resource with id = [[+id]] is not a product.';
$_lang['ms3_err_processor_combo_required'] = 'This processor requires combo: true.';

$_lang['ms3_err_category_id_required'] = 'Category ID is required';
$_lang['ms3_err_category_nf'] = 'Category not found';
$_lang['ms3_err_category_products_list_service'] = 'Category products list service is not available';
$_lang['ms3_err_items_required'] = 'Items array is required';
$_lang['ms3_err_method_required'] = 'Method is required';
$_lang['ms3_err_unknown_method'] = 'Unknown method';
$_lang['ms3_err_access_denied_permission'] = 'Access denied. Required permission: [[+permission]]';
$_lang['ms3_err_product_ids_required'] = 'Product IDs array is required';
$_lang['ms3_err_product_ids_invalid'] = 'No valid product IDs provided';
$_lang['ms3_err_category_products_no_updates'] = 'No products were updated';
$_lang['ms3_err_product_id_required'] = 'Product ID is required';
$_lang['ms3_err_product_nf'] = 'Product not found';
$_lang['ms3_err_product_update_failed'] = 'Failed to update product';
$_lang['ms3_err_catalog_parents_invalid'] = 'Invalid parents filter';
$_lang['ms3_err_catalog_parents_limit'] = 'Too many parent category IDs';
$_lang['ms3_err_catalog_price_invalid'] = 'Invalid price filter';
$_lang['ms3_err_catalog_price_range'] = 'price_max must be greater than or equal to price_min';
$_lang['ms3_err_catalog_stock_invalid'] = 'Invalid stock_min filter';
$_lang['ms3_err_catalog_vendor_invalid'] = 'Invalid vendor_id filter';
$_lang['ms3_err_catalog_vendor_limit'] = 'Too many vendor IDs';
$_lang['ms3_err_catalog_options_json'] = 'options must be a JSON object or map';
$_lang['ms3_err_catalog_options_limit'] = 'Too many option filters or values';
$_lang['ms3_err_catalog_option_key_invalid'] = 'Invalid option key';
$_lang['ms3_err_catalog_option_value_invalid'] = 'Invalid option value';
$_lang['ms3_err_catalog_option_unknown'] = 'Unknown option key';
$_lang['ms3_err_catalog_facet_keys_invalid'] = 'Invalid facet keys parameter';
$_lang['ms3_err_catalog_facet_keys_limit'] = 'Too many facet option keys';
$_lang['ms3_category_products_reordered'] = 'Products reordered successfully';
$_lang['ms3_category_product_published'] = 'Product published';
$_lang['ms3_category_product_unpublished'] = 'Product unpublished';
$_lang['ms3_category_products_updated'] = '[[+count]] products updated';

$_lang['ms3_email_subject_new_user'] = 'You placed order #[[+num]] on site [[++site_name]]';
$_lang['ms3_email_subject_new_manager'] = 'You have new order #[[+num]]';
$_lang['ms3_email_subject_paid_user'] = 'You paid for order #[[+num]]';
$_lang['ms3_email_subject_paid_manager'] = 'Order #[[+num]] was paid';
$_lang['ms3_email_subject_sent_user'] = 'Your order #[[+num]] was sent';
$_lang['ms3_email_subject_cancelled_user'] = 'Your order #[[+num]] was cancelled';

$_lang['ms3_payment_link'] = 'If you accidentally interrupted payment procedure, you can always <a href="[[+link]]" style="color:#348eda;">continue it via this link</a>.';

$_lang['ms3_category_err_ns'] = 'Category not selected';
$_lang['ms3_option_err_ns'] = 'Option not selected';
$_lang['ms3_option_err_nf'] = 'Option not found';
$_lang['ms3_option_err_ae'] = 'Option already exists';
$_lang['ms3_option_err_save'] = 'Error saving option';
$_lang['ms3_option_err_reserved_key'] = 'Such option key cannot be used';
$_lang['ms3_option_err_invalid_key'] = 'Invalid key for option';

$_lang['ms3_notifications'] = 'Notifications';
$_lang['ms3_notifications_desc'] = 'Order notification settings';
$_lang['ms3_help'] = 'Help and support';
$_lang['ms3_help_desc'] = 'Useful links and information';

$_lang['ms3_error'] = 'Error';
$_lang['ms3_vuetools_required'] = 'VueTools package is required for MiniShop3. Please install it via Package Manager.';

$_lang['ms3_mgr_order_recalc_invalid_mode'] = 'Invalid order cost recalculation mode.';
$_lang['ms3_mgr_order_recalc_manual_delivery_missing'] = 'Manual delivery cost (manual_delivery_cost) is required in manual mode.';
$_lang['ms3_order_cost_recalc_success'] = 'Order cost recalculated';
$_lang['ms3_order_finalize_cost_recalc_required'] =
    'Recalculate order cost before finalizing: the selected delivery or payment requires manual cost or force_provider mode.';
