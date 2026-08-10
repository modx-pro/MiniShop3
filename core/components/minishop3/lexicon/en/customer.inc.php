<?php

/**
 * Default English Lexicon Entries for MiniShop3 customer
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

$_lang['ms3_err_token'] = 'Token not specified';
$_lang['ms3_err_token_invalid'] = 'Token not found or invalid';
$_lang['ms3_err_token_expired'] = 'Token has expired';
$_lang['ms3_customer_addresses'] = 'Customer addresses';
$_lang['ms3_customer_key_empty'] = 'Request key missing';
$_lang['ms3_err_customer_nf'] = 'Customer profile not found';

$_lang['ms3_customer_comment'] = 'Comment';
$_lang['ms3_customer_first_name'] = 'First name';
$_lang['ms3_customer_last_name'] = 'Last name';
$_lang['ms3_customer_email'] = 'Email';
$_lang['ms3_customer_phone'] = 'Phone';
$_lang['ms3_customer_index'] = 'Postal code';
$_lang['ms3_customer_country'] = 'Country';
$_lang['ms3_customer_region'] = 'Region';
$_lang['ms3_customer_metro'] = 'Metro';
$_lang['ms3_customer_city'] = 'City';
$_lang['ms3_customer_street'] = 'Street';
$_lang['ms3_customer_building'] = 'Building';
$_lang['ms3_customer_room'] = 'Apt.';
$_lang['ms3_customer_entrance'] = 'Entrance';
$_lang['ms3_customer_floor'] = 'Floor';

// Authentication & Registration
$_lang['ms3_customer_guest'] = 'Guest';
$_lang['ms3_customer_password'] = 'Password';
$_lang['ms3_customer_password_confirm'] = 'Confirm Password';
$_lang['ms3_customer_register_success'] = 'Registration successful';
$_lang['ms3_customer_register_success_login_required'] = 'Registration successful. Please sign in with your email and password.';
$_lang['ms3_customer_login_success'] = 'You have successfully logged in';
$_lang['ms3_customer_password_recovery_not_available'] = 'Password recovery is not available yet';
$_lang['ms3_customer_logout'] = 'Logout';
$_lang['ms3_customer_logout_success'] = 'You have been logged out';
$_lang['ms3_customer_logout_confirm'] = 'Are you sure you want to logout?';

// Errors - Authentication
$_lang['ms3_customer_err_login_required'] = 'Please provide email and password';
$_lang['ms3_customer_err_login_invalid'] = 'Invalid email or password';
$_lang['ms3_customer_err_login_blocked'] = 'This account is temporarily blocked. Try again later.';
$_lang['ms3_customer_err_login_inactive'] = 'This account is inactive. Contact the store administrator.';
$_lang['ms3_customer_err_login_rate_limit'] = 'Too many login attempts ({attempts}/{max}). Try again in {minutes} minutes.';
$_lang['ms3_customer_err_email_required'] = 'Email is required';
$_lang['ms3_customer_err_email_invalid'] = 'Invalid email format';
$_lang['ms3_customer_err_email_exists'] = 'A user with this email is already registered';
$_lang['ms3_customer_err_phone_exists'] = 'A user with this phone number is already registered';
$_lang['ms3_customer_err_password_required'] = 'Password is required';
$_lang['ms3_customer_err_password_too_short'] = 'Password must be at least {length} characters long';
$_lang['ms3_customer_err_password_no_uppercase'] = 'Password must contain at least one uppercase letter';
$_lang['ms3_customer_err_password_no_number'] = 'Password must contain at least one number';
$_lang['ms3_customer_err_password_no_special'] = 'Password must contain at least one special character';
$_lang['ms3_customer_err_password_mismatch'] = 'Passwords do not match';
$_lang['ms3_customer_err_privacy_required'] = 'Privacy policy acceptance is required';
$_lang['ms3_customer_err_token_required'] = 'Token is required';
$_lang['ms3_customer_err_token_invalid'] = 'Invalid or expired token';
$_lang['ms3_customer_err_token_create'] = 'Error creating token';
$_lang['ms3_customer_err_save'] = 'Error saving data';
$_lang['ms3_customer_err_register_rate_limit'] = 'Registration limit exceeded. Please try again later.';
$_lang['ms3_customer_err_field_not_allowed'] = 'This field cannot be changed through the quick profile endpoint';

// Email Verification
$_lang['ms3_customer_email_verify_success'] = 'Email successfully verified';
$_lang['ms3_customer_err_email_verification_invalid'] = 'Invalid or expired verification token';
$_lang['ms3_email_verification_sent'] = 'Verification email has been sent';
$_lang['ms3_email_verification_cooldown'] = 'Please wait [[+seconds]] seconds before requesting another email';
$_lang['ms3_email_verification_send_failed'] = 'Failed to send verification email';
$_lang['ms3_email_already_verified'] = 'Email is already verified';
$_lang['ms3_email_verification_subject'] = '[[+site]]: Email Verification';
$_lang['ms3_email_verification_body'] = 'Hello, [[+first_name]]!

To verify your email address, please click the following link:
[[+url]]

This link is valid for [[+ttl_hours]] hours.

Best regards,
[[+site]]';

// Password Reset
$_lang['ms3_customer_forgot_password_success'] = 'Password reset instructions have been sent to your email';
$_lang['ms3_customer_err_forgot_password_rate_limit'] = 'Too many requests. Please try again in an hour.';
$_lang['ms3_customer_err_forgot_password_email_cooldown'] = 'Email already sent. Please try again in 5 minutes.';
$_lang['ms3_customer_err_reset_password_rate_limit'] = 'Too many password reset attempts ({attempts}/{max}). Try again in {minutes} minutes.';
$_lang['ms3_password_reset_subject'] = '[[+site]]: Password Reset';
$_lang['ms3_password_reset_body'] = 'Hello, [[+first_name]]!

To reset your password, please click the following link:
[[+url]]

This link is valid for [[+ttl_minutes]] minutes.

If you did not request a password reset, please ignore this email.

Best regards,
[[+site]]';
$_lang['ms3_password_reset_complete'] = 'Password successfully changed';

// Welcome Email
$_lang['ms3_customer_welcome_subject'] = '[[+site]]: Welcome!';
$_lang['ms3_customer_welcome_body'] = 'Hello!

You have been registered on [[+site]].

Email: [[+email]]
Password: [[+password]]

We recommend changing your password after first login.

Best regards,
[[+site]]';

// Customer Account Pages
$_lang['ms3_customer_err_invalid_service'] = 'Unknown service: [[+service]]';
$_lang['ms3_customer_account_title'] = 'My Account';
$_lang['ms3_customer_err_validation'] = 'Validation error';

// Unauthorized Page
$_lang['ms3_customer_unauthorized_title'] = 'Authorization Required';
$_lang['ms3_customer_unauthorized_message'] = 'You need to log in or register to access this page.';
$_lang['ms3_customer_login'] = 'Log In';
$_lang['ms3_customer_register'] = 'Register';
$_lang['ms3_customer_account'] = 'My Account';

// Login/Register Forms
$_lang['ms3_customer_email_placeholder'] = 'example@domain.com';
$_lang['ms3_customer_password_placeholder'] = 'Enter password';
$_lang['ms3_customer_password_confirm_placeholder'] = 'Repeat password';
$_lang['ms3_customer_first_name_placeholder'] = 'John';
$_lang['ms3_customer_last_name_placeholder'] = 'Doe';
$_lang['ms3_customer_phone_placeholder'] = '+1 (555) 123-4567';
$_lang['ms3_customer_remember_me'] = 'Remember me';
$_lang['ms3_customer_forgot_password'] = 'Forgot password?';
$_lang['ms3_customer_password_hint'] = 'At least 8 characters';
$_lang['ms3_customer_privacy_accept'] = 'I agree to the privacy policy';
$_lang['ms3_customer_err_register_required'] = 'Please enter email and password to register';

// Profile Page
$_lang['ms3_customer_profile_title'] = 'My Profile';
$_lang['ms3_customer_profile_updated'] = 'Profile successfully updated';
$_lang['ms3_customer_profile_save'] = 'Save Changes';
$_lang['ms3_customer_birthday'] = 'Date of Birth';
$_lang['ms3_customer_gender'] = 'Gender';
$_lang['ms3_customer_gender_not_specified'] = 'Not specified';
$_lang['ms3_customer_gender_male'] = 'Male';
$_lang['ms3_customer_gender_female'] = 'Female';
$_lang['ms3_customer_email_verified'] = 'Verified';
$_lang['ms3_customer_email_not_verified'] = 'Email not verified';
$_lang['ms3_customer_email_verified_at'] = 'Verified on {date}';
$_lang['ms3_customer_email_send_verification'] = 'Send Verification Email';
$_lang['ms3_customer_email_sending'] = 'Sending';
$_lang['ms3_customer_phone_verified'] = 'Verified';
$_lang['ms3_customer_phone_not_verified'] = 'Not verified';
$_lang['ms3_customer_phone_verified_at'] = 'Verified on {date}';
$_lang['ms3_customer_phone_verification_soon'] = 'Phone verification will be available soon';

// Addresses Page
$_lang['ms3_customer_addresses_title'] = 'My Addresses';
$_lang['ms3_customer_addresses_empty'] = 'You have no saved addresses yet';
$_lang['ms3_customer_address_add'] = 'Add Address';
$_lang['ms3_customer_address_edit'] = 'Edit';
$_lang['ms3_customer_address_delete'] = 'Delete';
$_lang['ms3_customer_address_delete_confirm'] = 'Are you sure you want to delete this address?';
$_lang['ms3_customer_address_default'] = 'Default';
$_lang['ms3_customer_address_set_default'] = 'Set as default';
$_lang['ms3_customer_address_set_default_confirm'] = 'Set this address as default?';
$_lang['ms3_customer_address_name'] = 'Address Name';
$_lang['ms3_customer_address_name_placeholder'] = 'E.g.: Home, Office, Cottage';
$_lang['ms3_customer_address_name_help'] = 'Optional. If not specified, will be generated automatically.';
$_lang['ms3_customer_address_comment_help'] = 'Additional information for courier';
$_lang['ms3_customer_err_address_not_found'] = 'Address not found';
$_lang['ms3_customer_err_address_id_not_specified'] = 'Address ID not specified';
$_lang['ms3_customer_address_updated'] = 'Address successfully updated';
$_lang['ms3_customer_address_added'] = 'Address successfully added';
$_lang['ms3_customer_address_deleted'] = 'Address successfully deleted';
$_lang['ms3_customer_address_default_set'] = 'Default address set successfully';
$_lang['ms3_customer_address_already_exists'] = 'This address already exists';
$_lang['ms3_customer_address_creation_error'] = 'Address creation error';
$_lang['ms3_customer_address_update_error'] = 'Address update error';
$_lang['ms3_customer_address_delete_error'] = 'Failed to delete address';
$_lang['ms3_customer_address_default_error'] = 'Failed to set default address';
$_lang['ms3_customer_err_not_authorized'] = 'Customer not authorized';
$_lang['ms3_customer_err_field_required'] = 'Field is required';
$_lang['ms3_customer_profile_update_error'] = 'Profile update error';
$_lang['ms3_customer_err_occurred'] = 'An error occurred';
$_lang['ms3_customer_err_occurred_saving'] = 'An error occurred while saving';
$_lang['ms3_customer_cancel'] = 'Cancel';
$_lang['ms3_customer_save'] = 'Save';

// Orders Page
$_lang['ms3_customer_orders_title'] = 'My Orders';
$_lang['ms3_customer_orders_empty'] = 'You have no orders yet';
$_lang['ms3_customer_orders_filter_by_status'] = 'Filter by status';
$_lang['ms3_customer_orders_all_statuses'] = 'All statuses';
$_lang['ms3_customer_orders_reset_filter'] = 'Reset';
$_lang['ms3_customer_order_num'] = 'Order Number';
$_lang['ms3_customer_order_date'] = 'Date';
$_lang['ms3_customer_order_status'] = 'Status';
$_lang['ms3_customer_order_total'] = 'Total';
$_lang['ms3_customer_order_view'] = 'View Details';
$_lang['ms3_customer_orders_pagination'] = 'Order navigation';
$_lang['ms3_customer_orders_prev'] = 'Previous';
$_lang['ms3_customer_orders_next'] = 'Next';
$_lang['ms3_customer_orders_total'] = 'Total orders: {total}';
$_lang['ms3_customer_orders_back'] = 'Back to list';
$_lang['ms3_customer_order_title'] = 'Order';
$_lang['ms3_customer_order_created'] = 'Order date';
$_lang['ms3_customer_order_cancel'] = 'Cancel order';
$_lang['ms3_customer_order_cancel_confirm'] = 'Are you sure you want to cancel this order?';
$_lang['ms3_customer_order_cancelled'] = 'Order cancelled';
$_lang['ms3_customer_order_cancel_err_unauthorized'] = 'Authorization required';
$_lang['ms3_customer_order_cancel_err_no_order'] = 'Order ID is required';
$_lang['ms3_customer_order_cancel_err_not_found'] = 'Order not found';
$_lang['ms3_customer_order_cancel_err_status'] = 'This order cannot be cancelled';
$_lang['ms3_customer_order_cancel_err_failed'] = 'Failed to cancel order';
$_lang['ms3_customer_order_err_unauthorized'] = 'Authorization required';
$_lang['ms3_customer_order_err_no_id'] = 'Order ID is required';
$_lang['ms3_customer_order_err_not_found'] = 'Order not found';
