<?php

/**
 * Order English Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

// Successful operations
$_lang['ms3_order_get_success'] = 'Order data retrieved';
$_lang['ms3_order_getcost_success'] = 'Cost calculated';
$_lang['ms3_order_set_success'] = 'Order data saved';
$_lang['ms3_order_clean_success'] = 'Order form cleared';
$_lang['ms3_order_submit_success'] = 'Order submitted';
$_lang['ms3_order_add_success'] = 'Order field updated';
$_lang['ms3_order_remove_success'] = 'Order field removed';

// Validation errors
$_lang['ms3_order_err_empty'] = 'Cart is empty';
$_lang['ms3_order_err_requires'] = 'Required fields are not filled';
$_lang['ms3_order_err_delivery'] = 'Delivery method is not selected';
$_lang['ms3_order_err_payment'] = 'Payment method is not selected';
$_lang['ms3_order_err_payment_not_found'] = 'Payment method not found or inactive';
$_lang['ms3_order_err_payment_delivery'] = 'Payment method is not available for the selected delivery method';
$_lang['ms3_order_delivery_id_nf'] = 'Delivery method not found';
$_lang['ms3_order_payment_id_nf'] = 'Payment method not found';

// Customer and user errors
$_lang['ms3_err_customer_nf'] = 'Customer not found';
$_lang['ms3_err_user_nf'] = 'User not found';
$_lang['ms3_err_order_load'] = 'Error loading order. Please try again later.';

// General errors
$_lang['ms3_err_unknown'] = 'Unknown error. Please try again later or contact the administrator.';

// Order finalization (admin)
$_lang['ms3_order_finalized'] = 'Order successfully finalized';
$_lang['ms3_order_err_nf'] = 'Order not found';
$_lang['ms3_order_err_already_finalized'] = 'Order is already finalized';
$_lang['ms3_order_err_validation'] = 'Order data validation error';
$_lang['ms3_order_finalize_btn'] = 'Finalize Order';
$_lang['ms3_order_finalize_info'] = 'After preparing the order draft, click the "Finalize Order" button to properly process the order in the system';
$_lang['ms3_order_finalize_confirm'] = 'Are you sure you want to finalize this order?';
$_lang['ms3_order_finalize_confirm_desc'] = 'After finalization, the order will receive a number, status will change to "New", and notifications will be sent.';

// Field validation errors (for finalization)
$_lang['ms3_order_err_products'] = 'Order has no products';
$_lang['ms3_order_err_delivery_id'] = 'Delivery method is not selected';
$_lang['ms3_order_err_payment_id'] = 'Payment method is not selected';
$_lang['ms3_order_err_customer_id'] = 'Customer is not specified';

// Programmatic / sessionless order API (#507)
$_lang['ms3_order_err_idempotency_key_required'] = 'Idempotency key is required';
$_lang['ms3_order_err_products_required'] = 'At least one product snapshot is required';
$_lang['ms3_order_err_programmatic_create'] = 'Failed to create programmatic order';
$_lang['ms3_order_programmatic_created'] = 'Order created programmatically';
$_lang['ms3_order_programmatic_idempotent'] = 'Existing order returned for idempotency key';
