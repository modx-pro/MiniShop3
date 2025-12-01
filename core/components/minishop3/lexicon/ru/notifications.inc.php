<?php
/**
 * MiniShop3 Notification Center Lexicon (Russian)
 *
 * @package minishop3
 * @subpackage lexicon
 * @language ru
 */

// Меню и заголовки
$_lang['ms3_notifications'] = 'Уведомления';
$_lang['ms3_notifications_desc'] = 'Управление настройками уведомлений';
$_lang['ms3_notifications_title'] = 'Центр уведомлений';

// Поля формы
$_lang['ms3_notification_event'] = 'Событие';
$_lang['ms3_notification_status'] = 'Статус заказа';
$_lang['ms3_notification_recipient'] = 'Получатель';
$_lang['ms3_notification_channel'] = 'Канал';
$_lang['ms3_notification_enabled'] = 'Включено';
$_lang['ms3_notification_subject'] = 'Тема письма';
$_lang['ms3_notification_template'] = 'Шаблон (чанк)';
$_lang['ms3_notification_delay'] = 'Задержка';
$_lang['ms3_notification_position'] = 'Позиция';

// Подсказки к полям
$_lang['ms3_notification_subject_placeholder'] = 'Заказ #{$num} - изменение статуса';
$_lang['ms3_notification_subject_hint'] = 'Поддерживается синтаксис Fenom: {$num}, {\'ms3_order\' | lexicon}';
$_lang['ms3_notification_template_placeholder'] = 'tpl.msEmail.order.new';
$_lang['ms3_notification_template_hint'] = 'Имя чанка для тела письма. Оставьте пустым для стандартного шаблона.';

// Типы событий
$_lang['ms3_notification_event_status_changed'] = 'Смена статуса заказа';
$_lang['ms3_notification_event_order_created'] = 'Создание заказа';

// Типы получателей
$_lang['ms3_notification_recipient_customer'] = 'Клиент';
$_lang['ms3_notification_recipient_manager'] = 'Менеджер';

// Значения
$_lang['ms3_notification_all_statuses'] = 'Все статусы';

// Действия
$_lang['ms3_notification_add'] = 'Добавить уведомление';
$_lang['ms3_notification_edit'] = 'Редактировать уведомление';

// Сообщения
$_lang['ms3_notification_created'] = 'Уведомление успешно создано';
$_lang['ms3_notification_updated'] = 'Уведомление успешно обновлено';
$_lang['ms3_notification_deleted'] = 'Уведомление удалено';
$_lang['ms3_notification_enabled'] = 'Уведомление включено';
$_lang['ms3_notification_disabled'] = 'Уведомление отключено';
$_lang['ms3_notification_delete_confirm'] = 'Вы уверены, что хотите удалить это уведомление?';
