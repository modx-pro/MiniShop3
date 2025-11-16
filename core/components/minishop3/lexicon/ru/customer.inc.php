<?php

/**
 * Default Russian Lexicon Entries for MiniShop3 customer
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

$_lang['ms3_err_token'] = 'Не указан токен';
$_lang['ms3_customer'] = 'Покупатель';
$_lang['ms3_customer_addresses'] = 'Адреса покупателя';
$_lang['ms3_customer_key_empty'] = 'Отсутствует ключ запроса';
$_lang['ms3_err_customer_nf'] = 'Профиль покупателя не найден';

$_lang['ms3_customer_comment'] = 'Комментарий';
$_lang['ms3_customer_first_name'] = 'Имя';
$_lang['ms3_customer_last_name'] = 'Фамилия';
$_lang['ms3_customer_email'] = 'Email';
$_lang['ms3_customer_phone'] = 'Телефон';
$_lang['ms3_customer_index'] = 'Почтовый индекс';
$_lang['ms3_customer_country'] = 'Страна';
$_lang['ms3_customer_region'] = 'Область';
$_lang['ms3_customer_metro'] = 'Метро';
$_lang['ms3_customer_city'] = 'Город';
$_lang['ms3_customer_street'] = 'Улица';
$_lang['ms3_customer_building'] = 'Дом';
$_lang['ms3_customer_room'] = 'Кв.';
$_lang['ms3_customer_entrance'] = 'Подъезд';
$_lang['ms3_customer_floor'] = 'Этаж';

// Authentication & Registration
$_lang['ms3_customer_password'] = 'Пароль';
$_lang['ms3_customer_password_confirm'] = 'Подтверждение пароля';
$_lang['ms3_customer_register_success'] = 'Регистрация прошла успешно';
$_lang['ms3_customer_login_success'] = 'Вы успешно вошли в систему';
$_lang['ms3_customer_logout_success'] = 'Вы вышли из системы';

// Errors - Authentication
$_lang['ms3_customer_err_login_required'] = 'Укажите email и пароль';
$_lang['ms3_customer_err_login_invalid'] = 'Неверный email или пароль';
$_lang['ms3_customer_err_login_rate_limit'] = 'Превышен лимит попыток входа ({attempts}/{max}). Попробуйте через {minutes} минут.';
$_lang['ms3_customer_err_email_required'] = 'Email обязателен для заполнения';
$_lang['ms3_customer_err_email_invalid'] = 'Указан некорректный email';
$_lang['ms3_customer_err_email_exists'] = 'Пользователь с таким email уже зарегистрирован';
$_lang['ms3_customer_err_phone_exists'] = 'Пользователь с таким телефоном уже зарегистрирован';
$_lang['ms3_customer_err_password_required'] = 'Пароль обязателен для заполнения';
$_lang['ms3_customer_err_password_too_short'] = 'Пароль должен содержать минимум {length} символов';
$_lang['ms3_customer_err_password_no_uppercase'] = 'Пароль должен содержать хотя бы одну заглавную букву';
$_lang['ms3_customer_err_password_no_number'] = 'Пароль должен содержать хотя бы одну цифру';
$_lang['ms3_customer_err_password_no_special'] = 'Пароль должен содержать хотя бы один спецсимвол';
$_lang['ms3_customer_err_password_mismatch'] = 'Пароли не совпадают';
$_lang['ms3_customer_err_privacy_required'] = 'Необходимо согласие на обработку персональных данных';
$_lang['ms3_customer_err_token_required'] = 'Токен не указан';
$_lang['ms3_customer_err_token_invalid'] = 'Неверный или истекший токен';
$_lang['ms3_customer_err_token_create'] = 'Ошибка создания токена';
$_lang['ms3_customer_err_save'] = 'Ошибка сохранения данных';
$_lang['ms3_customer_err_register_rate_limit'] = 'Превышен лимит регистраций. Попробуйте позже.';

// Email Verification
$_lang['ms3_customer_email_verified'] = 'Email успешно подтвержден';
$_lang['ms3_customer_err_email_verification_invalid'] = 'Неверный или истекший токен подтверждения';
$_lang['ms3_email_verification_sent'] = 'Письмо с подтверждением отправлено на ваш email';
$_lang['ms3_email_verification_cooldown'] = 'Повторная отправка возможна через {seconds} секунд';
$_lang['ms3_email_verification_send_failed'] = 'Ошибка отправки письма';
$_lang['ms3_email_already_verified'] = 'Email уже подтвержден';
$_lang['ms3_email_verification_subject'] = '{site}: Подтверждение email';
$_lang['ms3_email_verification_body'] = 'Здравствуйте, {first_name}!

Для подтверждения вашего email адреса перейдите по ссылке:
{url}

Ссылка действительна в течение {ttl_hours} часов.

С уважением,
{site}';

// Password Reset
$_lang['ms3_customer_forgot_password_success'] = 'Инструкции по восстановлению пароля отправлены на email';
$_lang['ms3_customer_err_forgot_password_rate_limit'] = 'Превышен лимит запросов. Попробуйте через час.';
$_lang['ms3_customer_err_forgot_password_email_cooldown'] = 'Письмо уже было отправлено. Попробуйте через 5 минут.';
$_lang['ms3_password_reset_subject'] = '{site}: Восстановление пароля';
$_lang['ms3_password_reset_body'] = 'Здравствуйте, {first_name}!

Для сброса пароля перейдите по ссылке:
{url}

Ссылка действительна в течение {ttl_minutes} минут.

Если вы не запрашивали восстановление пароля, просто проигнорируйте это письмо.

С уважением,
{site}';
$_lang['ms3_password_reset_complete'] = 'Пароль успешно изменен';

// Welcome Email
$_lang['ms3_customer_welcome_subject'] = '{site}: Добро пожаловать!';
$_lang['ms3_customer_welcome_body'] = 'Здравствуйте!

Вы зарегистрированы на сайте {site}.

Email: {email}
Пароль: {password}

Рекомендуем сменить пароль после первого входа.

С уважением,
{site}';
