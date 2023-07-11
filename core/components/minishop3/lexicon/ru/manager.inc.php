<?php

/**
 * Manager Russian Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

$_lang['ms_menu_create'] = 'Создать';
$_lang['ms_menu_copy'] = 'Копировать';
$_lang['ms_menu_add'] = 'Добавить';
$_lang['ms_menu_update'] = 'Изменить';
$_lang['ms_menu_remove'] = 'Удалить';
$_lang['ms_menu_remove_multiple'] = 'Удалить выбранное';
$_lang['ms_menu_remove_confirm'] = 'Вы уверены, что хотите удалить эту запись?';
$_lang['ms_menu_remove_multiple_confirm'] = 'Вы уверены, что хотите удалить все выбранные записи?';
$_lang['ms_menu_enable'] = 'Включить';
$_lang['ms_menu_disable'] = 'Выключить';
$_lang['ms_menu_select_all'] = 'Выделить всё';
$_lang['ms_menu_clear_all'] = 'Очистить всё';

$_lang['ms_combo_select'] = 'Нажмите для выбора';
$_lang['ms_combo_select_status'] = 'Фильтр по статусу';

$_lang['ms_id'] = 'Id';
$_lang['ms_key'] = 'Ключ';
$_lang['ms_name'] = 'Имя';
$_lang['ms_caption'] = 'Заголовок';
$_lang['ms_color'] = 'Цвет';
$_lang['ms_country'] = 'Страна';
$_lang['ms_logo'] = 'Логотип';
$_lang['ms_address'] = 'Адрес';
$_lang['ms_phone'] = 'Телефон';
$_lang['ms_fax'] = 'Факс';
$_lang['ms_email'] = 'Email';
$_lang['ms_active'] = 'Включен';
$_lang['ms_required'] = 'Обязательный';
$_lang['ms_class'] = 'Класс-обработчик';
$_lang['ms_description'] = 'Описание';
$_lang['ms_num'] = 'Номер';
$_lang['ms_status'] = 'Статус';
$_lang['ms_count'] = 'Количество';
$_lang['ms_cost'] = 'Стоимость';
$_lang['ms_order_cost'] = 'Стоимость заказа';
$_lang['ms_cart_cost'] = 'Стоимость покупок';
$_lang['ms_delivery_cost'] = 'Стоимость доставки';
$_lang['ms_weight'] = 'Вес';
$_lang['ms_createdon'] = 'Дата создания';
$_lang['ms_updatedon'] = 'Дата изменения';
$_lang['ms_user'] = 'Пользователь';
$_lang['ms_timestamp'] = 'Метка времени';
$_lang['ms_order_log'] = 'История заказа';
$_lang['ms_order_products'] = 'Покупки';
$_lang['ms_action'] = 'Действие';
$_lang['ms_entry'] = 'Запись';
$_lang['ms_username'] = 'Логин';
$_lang['ms_fullname'] = 'Пользователь';
$_lang['ms_resource'] = 'Ресурс';

$_lang['ms_receiver'] = 'Получатель';
$_lang['ms_index'] = 'Индекс';
$_lang['ms_region'] = 'Область';
$_lang['ms_city'] = 'Город';
$_lang['ms_metro'] = 'Станция метро';
$_lang['ms_street'] = 'Улица';
$_lang['ms_building'] = 'Здание';
$_lang['ms_room'] = 'Кв.';
$_lang['ms_entrance'] = 'Подъезд';
$_lang['ms_floor'] = 'Этаж';
$_lang['ms_text_address'] = 'Адрес одной строкой';
$_lang['ms_comment'] = 'Комментарий';
$_lang['ms_order_comment'] = 'Комментарий оператора';

$_lang['ms_email_user'] = 'Письмо покупателю';
$_lang['ms_email_manager'] = 'Письмо менеджеру';
$_lang['ms_subject_user'] = 'Тема письма покупателю';
$_lang['ms_subject_manager'] = 'Тема письма менеджеру';
$_lang['ms_body_user'] = 'Чанк письма покупателю';
$_lang['ms_body_manager'] = 'Чанк письма менеджеру';
$_lang['ms_status_final'] = 'Итоговый';
$_lang['ms_status_final_help'] = 'Если статус является итоговым - его нельзя переключить на другой.';
$_lang['ms_status_fixed'] = 'Фиксирует';
$_lang['ms_status_fixed_help'] = 'Фиксирующий статус запрещает переключение на статусы, которые идут в таблице раньше него.';
$_lang['ms_options'] = 'Опции';
$_lang['ms_add_cost'] = 'Доп. стоимость';
$_lang['ms_add_cost_help'] = 'Дополнительная стоимость доставки или оплаты. Может быть отрицательной, можно указывать проценты.';
$_lang['ms_weight_price'] = 'Стоимость ед/вес';
$_lang['ms_weight_price_help'] = 'Добавочная стоимость доставки за единицу веса.<br/>Может быть использовано в кастомных классах.';
$_lang['ms_distance_price'] = 'Стоимость ед/рст';
$_lang['ms_distance_price_help'] = 'Добавочная стоимость доставки за единицу расстояния.<br/>Может быть использовано в кастомных классах.';
$_lang['ms_order_requires'] = 'Обязательные поля';
$_lang['ms_order_requires_help'] = 'При оформлении заказа, кастомный класс может требовать заполнение этих полей.';
$_lang['ms_rank'] = 'Порядок';
$_lang['ms_free_delivery_amount'] = 'Бесплатная доставка от суммы заказа';
$_lang['ms_free_delivery_amount_help'] = 'При достижении суммы заказа данного значения, доставка будет бесплатной. Если класс доставки изменен и/или у вас установлены компоненты, которые могут повлиять на стоимость заказа, то данное поле может не учитываться';

$_lang['ms_orders_selected_status'] = 'Сменить статус';

$_lang['ms_link_name'] = 'Имя связи';
$_lang['ms_link_one_to_one'] = 'Один к одному';
$_lang['ms_link_one_to_one_desc'] = 'Равная связь двух товаров. Если вы хотите связать более чем 2 товара, нужно использовать "многие ко многим".';
$_lang['ms_link_one_to_many'] = 'Один ко многим';
$_lang['ms_link_one_to_many_desc'] = 'Связь главного товара с подчинёнными. Например, товар является набором других товаров. Хорошо подойдёт для указания рекомендованных товаров.';
$_lang['ms_link_many_to_one'] = 'Многие к одному';
$_lang['ms_link_many_to_one_desc'] = 'Связь подчинённых товаров с главным, при этом, друг с другом они не связаны. Например, товары, входят в определённый набор.';
$_lang['ms_link_many_to_many'] = 'Многие ко многим';
$_lang['ms_link_many_to_many_desc'] = 'Равная связь множества товаров. Все товары группы связаны друг с другом и при добавлении новой связи одному из них - её получают и другие. Типичное применение: связь по какому-то параметру, например цвету, размеру, языковой версии и т.д.';
$_lang['ms_link_master'] = 'Главный товар';
$_lang['ms_link_slave'] = 'Подчинённый товар';

$_lang['ms_ft_active'] = 'Включена';
$_lang['ms_ft_caption'] = 'Название';
$_lang['ms_ft_description'] = 'Описание';
$_lang['ms_ft_measure_unit'] = 'Единица измерения';
$_lang['ms_ft_group'] = 'Группа';
$_lang['ms_ft_groups'] = 'Группы';
$_lang['ms_ft_nogroup'] = 'Без группы';
$_lang['ms_ft_name'] = 'Ключ';
$_lang['ms_ft_required'] = 'Обязательна';
$_lang['ms_ft_type'] = 'Тип характеристики';
$_lang['ms_ft_rank'] = 'Порядок сортировки';

$_lang['ms_ft_selected_delete'] = 'Убрать';
$_lang['ms_ft_selected_activate'] = 'Включить';
$_lang['ms_ft_selected_deactivate'] = 'Выключить';
$_lang['ms_ft_selected_require'] = 'Сделать обязательной';
$_lang['ms_ft_selected_unrequire'] = 'Сделать необязательной';
$_lang['ms_ft_selected_assign'] = 'Назначить в категорию';
$_lang['ms_options_remove_confirm'] = 'Вы уверены, что хотите удалить все выбранные опции? Значения этих опций в товарах будут удалены без возможности восстановления.';
$_lang['ms_category_options_assign'] = 'Назначить опции в категории.';

$_lang['ms_ft_textfield'] = 'Текстовое поле';
$_lang['ms_ft_numberfield'] = 'Числовое поле';
$_lang['ms_ft_textarea'] = 'Текстовая область';
$_lang['ms_ft_combobox'] = 'Выпадающий список';
$_lang['ms_ft_comboBoolean'] = 'Да/Нет';
$_lang['ms_ft_comboMultiple'] = 'Множественный список';
$_lang['ms_ft_comboOptions'] = 'Список с автодополнением';
$_lang['ms_ft_checkbox'] = 'Флажок';
$_lang['ms_ft_datefield'] = 'Дата';

$_lang['ms_orders_form_begin'] = 'Выбрать заказы с';
$_lang['ms_orders_form_end'] = 'Выбрать заказы по';
$_lang['ms_orders_form_status'] = 'Фильтр по статусу';
$_lang['ms_orders_form_search'] = 'Поиск (номер, email, комментарий)';
$_lang['ms_orders_form_customer'] = 'Фильтр по заказчику';
$_lang['ms_orders_form_context'] = 'Фильтр по контексту';
$_lang['ms_orders_form_selected_num'] = 'Выбрано заказов';
$_lang['ms_orders_form_selected_sum'] = 'на сумму, руб.';
$_lang['ms_orders_form_month_num'] = 'Выполнено за 30 дней';
$_lang['ms_orders_form_month_sum'] = 'на сумму, руб.';
$_lang['ms_orders_form_submit'] = 'Отправить';
$_lang['ms_orders_form_reset'] = 'Сброс';

$_lang['ms_tab_category'] = 'Категория';
$_lang['ms_tab_products'] = 'Товары';
$_lang['ms_tab_options'] = 'Свойства товаров';
$_lang['ms_tab_comments'] = 'Комментарии';
$_lang['ms_tab_product'] = 'Товар';
$_lang['ms_tab_product_data'] = 'Свойства товара';
$_lang['ms_tab_product_options'] = 'Опции товара';
$_lang['ms_tab_product_links'] = 'Связи';
$_lang['ms_tab_product_categories'] = 'Категории';
$_lang['ms_tab_product_gallery'] = 'Галерея';
