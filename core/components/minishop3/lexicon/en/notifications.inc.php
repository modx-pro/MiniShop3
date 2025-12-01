<?php
/**
 * MiniShop3 Notification Center Lexicon (English)
 *
 * @package minishop3
 * @subpackage lexicon
 * @language en
 */

// Menu and titles
$_lang['ms3_notifications'] = 'Notifications';
$_lang['ms3_notifications_desc'] = 'Manage notification settings';
$_lang['ms3_notifications_title'] = 'Notification Center';

// Form fields
$_lang['ms3_notification_event'] = 'Event';
$_lang['ms3_notification_status'] = 'Order Status';
$_lang['ms3_notification_recipient'] = 'Recipient';
$_lang['ms3_notification_channel'] = 'Channel';
$_lang['ms3_notification_enabled'] = 'Enabled';
$_lang['ms3_notification_subject'] = 'Subject';
$_lang['ms3_notification_template'] = 'Template (chunk)';
$_lang['ms3_notification_delay'] = 'Delay';
$_lang['ms3_notification_position'] = 'Position';

// Field hints
$_lang['ms3_notification_subject_placeholder'] = 'Order #{$num} - status change';
$_lang['ms3_notification_subject_hint'] = 'Supports Fenom syntax: {$num}, {\'ms3_order\' | lexicon}';
$_lang['ms3_notification_template_placeholder'] = 'tpl.msEmail.order.new';
$_lang['ms3_notification_template_hint'] = 'Chunk name for email body. Leave empty for default template.';

// Event types
$_lang['ms3_notification_event_status_changed'] = 'Order status changed';
$_lang['ms3_notification_event_order_created'] = 'Order created';

// Recipient types
$_lang['ms3_notification_recipient_customer'] = 'Customer';
$_lang['ms3_notification_recipient_manager'] = 'Manager';

// Values
$_lang['ms3_notification_all_statuses'] = 'All statuses';

// Actions
$_lang['ms3_notification_add'] = 'Add notification';
$_lang['ms3_notification_edit'] = 'Edit notification';

// Messages
$_lang['ms3_notification_created'] = 'Notification created successfully';
$_lang['ms3_notification_updated'] = 'Notification updated successfully';
$_lang['ms3_notification_deleted'] = 'Notification deleted';
$_lang['ms3_notification_enabled'] = 'Notification enabled';
$_lang['ms3_notification_disabled'] = 'Notification disabled';
$_lang['ms3_notification_delete_confirm'] = 'Are you sure you want to delete this notification?';
