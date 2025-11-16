<?php

/**
 * Default English Lexicon Entries for MiniShop3 customer
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

$_lang['ms3_err_token'] = 'Token not specified';
$_lang['ms3_customer'] = 'Customer';
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
$_lang['ms3_customer_password'] = 'Password';
$_lang['ms3_customer_password_confirm'] = 'Confirm Password';
$_lang['ms3_customer_register_success'] = 'Registration successful';
$_lang['ms3_customer_login_success'] = 'You have successfully logged in';
$_lang['ms3_customer_logout_success'] = 'You have been logged out';

// Errors - Authentication
$_lang['ms3_customer_err_login_required'] = 'Please provide email and password';
$_lang['ms3_customer_err_login_invalid'] = 'Invalid email or password';
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

// Email Verification
$_lang['ms3_customer_email_verified'] = 'Email successfully verified';
$_lang['ms3_customer_err_email_verification_invalid'] = 'Invalid or expired verification token';
$_lang['ms3_email_verification_sent'] = 'Verification email has been sent';
$_lang['ms3_email_verification_cooldown'] = 'Please wait {seconds} seconds before requesting another email';
$_lang['ms3_email_verification_send_failed'] = 'Failed to send verification email';
$_lang['ms3_email_already_verified'] = 'Email is already verified';
$_lang['ms3_email_verification_subject'] = '{site}: Email Verification';
$_lang['ms3_email_verification_body'] = 'Hello, {first_name}!

To verify your email address, please click the following link:
{url}

This link is valid for {ttl_hours} hours.

Best regards,
{site}';

// Password Reset
$_lang['ms3_customer_forgot_password_success'] = 'Password reset instructions have been sent to your email';
$_lang['ms3_customer_err_forgot_password_rate_limit'] = 'Too many requests. Please try again in an hour.';
$_lang['ms3_customer_err_forgot_password_email_cooldown'] = 'Email already sent. Please try again in 5 minutes.';
$_lang['ms3_password_reset_subject'] = '{site}: Password Reset';
$_lang['ms3_password_reset_body'] = 'Hello, {first_name}!

To reset your password, please click the following link:
{url}

This link is valid for {ttl_minutes} minutes.

If you did not request a password reset, please ignore this email.

Best regards,
{site}';
$_lang['ms3_password_reset_complete'] = 'Password successfully changed';

// Welcome Email
$_lang['ms3_customer_welcome_subject'] = '{site}: Welcome!';
$_lang['ms3_customer_welcome_body'] = 'Hello!

You have been registered on {site}.

Email: {email}
Password: {password}

We recommend changing your password after first login.

Best regards,
{site}';
