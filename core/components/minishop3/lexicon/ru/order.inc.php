<?php

/**
 * Order Russian Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

// Успешные операции
$_lang['ms3_order_get_success'] = 'Данные заказа получены';
$_lang['ms3_order_getcost_success'] = 'Стоимость рассчитана';
$_lang['ms3_order_set_success'] = 'Данные заказа сохранены';
$_lang['ms3_order_clean_success'] = 'Форма заказа очищена';
$_lang['ms3_order_submit_success'] = 'Заказ оформлен';
$_lang['ms3_order_add_success'] = 'Поле заказа обновлено';
$_lang['ms3_order_remove_success'] = 'Поле заказа удалено';

// Ошибки валидации
$_lang['ms3_order_err_empty'] = 'Корзина пуста';
$_lang['ms3_order_err_requires'] = 'Не заполнены обязательные поля';
$_lang['ms3_order_err_delivery'] = 'Не выбран способ доставки';
$_lang['ms3_order_err_payment'] = 'Не выбран способ оплаты';
$_lang['ms3_order_err_payment_not_found'] = 'Способ оплаты не найден или неактивен';
$_lang['ms3_order_err_payment_delivery'] = 'Способ оплаты недоступен для выбранной доставки';
$_lang['ms3_order_delivery_id_nf'] = 'Способ доставки не найден';
$_lang['ms3_order_payment_id_nf'] = 'Способ оплаты не найден';

// Ошибки клиентов и пользователей
$_lang['ms3_err_customer_nf'] = 'Покупатель не найден';
$_lang['ms3_err_user_nf'] = 'Пользователь не найден';
$_lang['ms3_err_order_load'] = 'Ошибка при загрузке заказа. Попробуйте позже.';

// Общие ошибки
$_lang['ms3_err_unknown'] = 'Неизвестная ошибка. Попробуйте повторить операцию позже или свяжитесь с администратором.';

// Финализация заказа (админка)
$_lang['ms3_order_finalized'] = 'Заказ успешно оформлен';
$_lang['ms3_order_err_nf'] = 'Заказ не найден';
$_lang['ms3_order_err_already_finalized'] = 'Заказ уже оформлен';
$_lang['ms3_order_err_validation'] = 'Ошибка валидации данных заказа';
$_lang['ms3_order_finalize_btn'] = 'Оформить заказ';
$_lang['ms3_order_finalize_info'] = 'После формирования черновика заказа нажмите кнопку «Оформить заказ» для правильного проведения заказа в системе';
$_lang['ms3_order_finalize_confirm'] = 'Вы уверены, что хотите оформить этот заказ?';
$_lang['ms3_order_finalize_confirm_desc'] = 'После оформления заказ получит номер, статус изменится на «Новый», и будут отправлены уведомления.';

// Ошибки валидации полей (для финализации)
$_lang['ms3_order_err_products'] = 'В заказе нет товаров';
$_lang['ms3_order_err_delivery_id'] = 'Не выбран способ доставки';
$_lang['ms3_order_err_payment_id'] = 'Не выбран способ оплаты';
$_lang['ms3_order_err_customer_id'] = 'Не указан покупатель';

// Программное / sessionless API заказа (#507)
$_lang['ms3_order_err_idempotency_key_required'] = 'Требуется ключ идемпотентности';
$_lang['ms3_order_err_products_required'] = 'Нужен хотя бы один снимок товара';
$_lang['ms3_order_err_programmatic_create'] = 'Не удалось создать заказ программно';
$_lang['ms3_order_programmatic_created'] = 'Заказ создан программно';
$_lang['ms3_order_programmatic_idempotent'] = 'Возвращён существующий заказ по ключу идемпотентности';
