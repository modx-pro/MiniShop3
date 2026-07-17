<?php

/**
 * Default Russian Lexicon Entries for MiniShop3 customer
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

$_lang['ms3_err_token'] = 'Не указан токен';
$_lang['ms3_err_token_invalid'] = 'Токен не найден или недействителен';
$_lang['ms3_err_token_expired'] = 'Срок действия токена истёк';
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
$_lang['ms3_customer_guest'] = 'Гость';
$_lang['ms3_customer_password'] = 'Пароль';
$_lang['ms3_customer_password_confirm'] = 'Подтверждение пароля';
$_lang['ms3_customer_register_success'] = 'Регистрация прошла успешно';
$_lang['ms3_customer_register_success_login_required'] = 'Регистрация прошла успешно. Войдите с email и паролем.';
$_lang['ms3_customer_login_success'] = 'Вы успешно вошли в систему';
$_lang['ms3_customer_password_recovery_not_available'] = 'Восстановление пароля пока недоступно';
$_lang['ms3_customer_logout'] = 'Выход';
$_lang['ms3_customer_logout_success'] = 'Вы вышли из системы';
$_lang['ms3_customer_logout_confirm'] = 'Вы действительно хотите выйти?';

// Errors - Authentication
$_lang['ms3_customer_err_login_required'] = 'Укажите email и пароль';
$_lang['ms3_customer_err_login_invalid'] = 'Неверный email или пароль';
$_lang['ms3_customer_err_login_blocked'] = 'Аккаунт временно заблокирован. Попробуйте позже.';
$_lang['ms3_customer_err_login_inactive'] = 'Аккаунт неактивен. Обратитесь к администратору магазина.';
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
$_lang['ms3_customer_err_field_not_allowed'] = 'Это поле нельзя изменить через быстрый профиль';

// Email Verification
$_lang['ms3_customer_email_verify_success'] = 'Email успешно подтвержден';
$_lang['ms3_customer_err_email_verification_invalid'] = 'Неверный или истекший токен подтверждения';
$_lang['ms3_email_verification_sent'] = 'Письмо с подтверждением отправлено на ваш email';
$_lang['ms3_email_verification_cooldown'] = 'Повторная отправка возможна через [[+seconds]] секунд';
$_lang['ms3_email_verification_send_failed'] = 'Ошибка отправки письма';
$_lang['ms3_email_already_verified'] = 'Email уже подтвержден';
$_lang['ms3_email_verification_subject'] = '[[+site]]: Подтверждение email';
$_lang['ms3_email_verification_body'] = 'Здравствуйте, [[+first_name]]!

Для подтверждения вашего email адреса перейдите по ссылке:
[[+url]]

Ссылка действительна в течение [[+ttl_hours]] часов.

С уважением,
[[+site]]';

// Password Reset
$_lang['ms3_customer_forgot_password_success'] = 'Инструкции по восстановлению пароля отправлены на email';
$_lang['ms3_customer_err_forgot_password_rate_limit'] = 'Превышен лимит запросов. Попробуйте через час.';
$_lang['ms3_customer_err_forgot_password_email_cooldown'] = 'Письмо уже было отправлено. Попробуйте через 5 минут.';
$_lang['ms3_customer_err_reset_password_rate_limit'] = 'Превышен лимит попыток сброса пароля ({attempts}/{max}). Попробуйте через {minutes} минут.';
$_lang['ms3_password_reset_subject'] = '[[+site]]: Восстановление пароля';
$_lang['ms3_password_reset_body'] = 'Здравствуйте, [[+first_name]]!

Для сброса пароля перейдите по ссылке:
[[+url]]

Ссылка действительна в течение [[+ttl_minutes]] минут.

Если вы не запрашивали восстановление пароля, просто проигнорируйте это письмо.

С уважением,
[[+site]]';
$_lang['ms3_password_reset_complete'] = 'Пароль успешно изменен';

// Welcome Email
$_lang['ms3_customer_welcome_subject'] = '[[+site]]: Добро пожаловать!';
$_lang['ms3_customer_welcome_body'] = 'Здравствуйте!

Вы зарегистрированы на сайте [[+site]].

Email: [[+email]]
Пароль: [[+password]]

Рекомендуем сменить пароль после первого входа.

С уважением,
[[+site]]';

// Customer Account Pages
$_lang['ms3_customer_err_invalid_service'] = 'Неизвестный сервис: [[+service]]';
$_lang['ms3_customer_account_title'] = 'Личный кабинет';
$_lang['ms3_customer_err_validation'] = 'Ошибка валидации данных';

// Unauthorized Page
$_lang['ms3_customer_unauthorized_title'] = 'Требуется авторизация';
$_lang['ms3_customer_unauthorized_message'] = 'Для доступа к этой странице необходимо войти в систему или зарегистрироваться.';
$_lang['ms3_customer_login'] = 'Войти';
$_lang['ms3_customer_register'] = 'Регистрация';
$_lang['ms3_customer_account'] = 'Личный кабинет';

// Login/Register Forms
$_lang['ms3_customer_email_placeholder'] = 'example@domain.com';
$_lang['ms3_customer_password_placeholder'] = 'Введите пароль';
$_lang['ms3_customer_password_confirm_placeholder'] = 'Повторите пароль';
$_lang['ms3_customer_first_name_placeholder'] = 'Иван';
$_lang['ms3_customer_last_name_placeholder'] = 'Иванов';
$_lang['ms3_customer_phone_placeholder'] = '+7 (900) 123-45-67';
$_lang['ms3_customer_remember_me'] = 'Запомнить меня';
$_lang['ms3_customer_forgot_password'] = 'Забыли пароль?';
$_lang['ms3_customer_password_hint'] = 'Минимум 8 символов';
$_lang['ms3_customer_privacy_accept'] = 'Я согласен с политикой конфиденциальности';
$_lang['ms3_customer_err_register_required'] = 'Укажите email и пароль для регистрации';

// Profile Page
$_lang['ms3_customer_profile_title'] = 'Мой профиль';
$_lang['ms3_customer_profile_updated'] = 'Профиль успешно обновлён';
$_lang['ms3_customer_profile_save'] = 'Сохранить изменения';
$_lang['ms3_customer_birthday'] = 'Дата рождения';
$_lang['ms3_customer_gender'] = 'Пол';
$_lang['ms3_customer_gender_not_specified'] = 'Не указан';
$_lang['ms3_customer_gender_male'] = 'Мужской';
$_lang['ms3_customer_gender_female'] = 'Женский';
$_lang['ms3_customer_email_verified'] = 'Подтверждён';
$_lang['ms3_customer_email_not_verified'] = 'Email не подтверждён';
$_lang['ms3_customer_email_verified_at'] = 'Подтверждён {date}';
$_lang['ms3_customer_email_send_verification'] = 'Отправить письмо подтверждения';
$_lang['ms3_customer_email_sending'] = 'Отправка';
$_lang['ms3_customer_phone_verified'] = 'Подтверждён';
$_lang['ms3_customer_phone_not_verified'] = 'Не подтверждён';
$_lang['ms3_customer_phone_verified_at'] = 'Подтверждён {date}';
$_lang['ms3_customer_phone_verification_soon'] = 'Функция подтверждения телефона будет доступна в ближайшее время';

// Addresses Page
$_lang['ms3_customer_addresses_title'] = 'Мои адреса';
$_lang['ms3_customer_addresses_empty'] = 'У вас пока нет сохранённых адресов';
$_lang['ms3_customer_address_add'] = 'Добавить адрес';
$_lang['ms3_customer_address_edit'] = 'Редактировать';
$_lang['ms3_customer_address_delete'] = 'Удалить';
$_lang['ms3_customer_address_delete_confirm'] = 'Вы уверены, что хотите удалить этот адрес?';
$_lang['ms3_customer_address_default'] = 'Основной';
$_lang['ms3_customer_address_set_default'] = 'Сделать основным';
$_lang['ms3_customer_address_set_default_confirm'] = 'Сделать этот адрес основным?';
$_lang['ms3_customer_address_name'] = 'Название адреса';
$_lang['ms3_customer_address_name_placeholder'] = 'Например: Дом, Работа, Дача';
$_lang['ms3_customer_address_name_help'] = 'Необязательно. Если не указано, будет сформировано автоматически.';
$_lang['ms3_customer_address_comment_help'] = 'Дополнительная информация для курьера';
$_lang['ms3_customer_err_address_not_found'] = 'Адрес не найден';
$_lang['ms3_customer_err_address_id_not_specified'] = 'Не указан идентификатор адреса';
$_lang['ms3_customer_address_updated'] = 'Адрес успешно обновлён';
$_lang['ms3_customer_address_added'] = 'Адрес успешно добавлен';
$_lang['ms3_customer_address_deleted'] = 'Адрес успешно удалён';
$_lang['ms3_customer_address_default_set'] = 'Адрес назначен основным';
$_lang['ms3_customer_address_already_exists'] = 'Такой адрес уже существует';
$_lang['ms3_customer_address_creation_error'] = 'Ошибка создания адреса';
$_lang['ms3_customer_address_update_error'] = 'Ошибка обновления адреса';
$_lang['ms3_customer_address_delete_error'] = 'Не удалось удалить адрес';
$_lang['ms3_customer_address_default_error'] = 'Не удалось назначить адрес основным';
$_lang['ms3_customer_err_not_authorized'] = 'Клиент не авторизован';
$_lang['ms3_customer_err_field_required'] = 'Поле обязательно для заполнения';
$_lang['ms3_customer_profile_update_error'] = 'Ошибка обновления профиля';
$_lang['ms3_customer_err_occurred'] = 'Произошла ошибка';
$_lang['ms3_customer_err_occurred_saving'] = 'Произошла ошибка при сохранении';
$_lang['ms3_customer_cancel'] = 'Отмена';
$_lang['ms3_customer_save'] = 'Сохранить';

// Orders Page
$_lang['ms3_customer_orders_title'] = 'Мои заказы';
$_lang['ms3_customer_orders_empty'] = 'У вас пока нет заказов';
$_lang['ms3_customer_orders_filter_by_status'] = 'Фильтр по статусу';
$_lang['ms3_customer_orders_all_statuses'] = 'Все статусы';
$_lang['ms3_customer_orders_reset_filter'] = 'Сбросить';
$_lang['ms3_customer_order_num'] = 'Номер заказа';
$_lang['ms3_customer_order_date'] = 'Дата';
$_lang['ms3_customer_order_status'] = 'Статус';
$_lang['ms3_customer_order_total'] = 'Сумма';
$_lang['ms3_customer_order_view'] = 'Подробнее';
$_lang['ms3_customer_orders_pagination'] = 'Навигация по заказам';
$_lang['ms3_customer_orders_prev'] = 'Предыдущая';
$_lang['ms3_customer_orders_next'] = 'Следующая';
$_lang['ms3_customer_orders_total'] = 'Всего заказов: {total}';
$_lang['ms3_customer_orders_back'] = 'Назад к списку';
$_lang['ms3_customer_order_title'] = 'Заказ';
$_lang['ms3_customer_order_created'] = 'Дата оформления';
$_lang['ms3_customer_order_cancel'] = 'Отменить заказ';
$_lang['ms3_customer_order_cancel_confirm'] = 'Вы уверены, что хотите отменить этот заказ?';
$_lang['ms3_customer_order_cancelled'] = 'Заказ отменён';
$_lang['ms3_customer_order_cancel_err_unauthorized'] = 'Требуется авторизация';
$_lang['ms3_customer_order_cancel_err_no_order'] = 'Не указан номер заказа';
$_lang['ms3_customer_order_cancel_err_not_found'] = 'Заказ не найден';
$_lang['ms3_customer_order_cancel_err_status'] = 'Этот заказ нельзя отменить';
$_lang['ms3_customer_order_cancel_err_failed'] = 'Не удалось отменить заказ';
$_lang['ms3_customer_order_err_unauthorized'] = 'Требуется авторизация';
$_lang['ms3_customer_order_err_no_id'] = 'Не указан номер заказа';
$_lang['ms3_customer_order_err_not_found'] = 'Заказ не найден';
