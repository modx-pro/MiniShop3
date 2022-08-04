<?php

/**
 * Default Russian Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

include_once('setting.inc.php');
$files = scandir(dirname(__FILE__));
foreach ($files as $file) {
    if (strpos($file, 'msp.') === 0) {
        @include_once($file);
    }
}

$_lang['miniShop3'] = 'MiniShop3';
$_lang['ms_menu_desc'] = 'Продвинутый интернет-магазин';
$_lang['ms_order'] = 'Заказ';
$_lang['ms_orders'] = 'Заказы';
$_lang['ms_orders_intro'] = 'Панель управления заказами. Вы можете выбирать сразу несколько заказов через Shift или Ctrl(Cmd).';
$_lang['ms_orders_desc'] = 'Управление заказами';
$_lang['ms_settings'] = 'Настройки';
$_lang['ms_settings_intro'] = 'Панель управления настройками магазина. Здесь вы можете указать способы оплаты, доставки и статусы заказов.';
$_lang['ms_settings_desc'] = 'Статусы заказов, параметры оплаты и доставки';
$_lang['ms_system_settings'] = 'Системные настройки';
$_lang['ms_system_settings_desc'] = 'Системные настройки MiniShop3';
$_lang['ms_payment'] = 'Оплата';
$_lang['ms_payments'] = 'Способы оплаты';
$_lang['ms_payments_intro'] = 'Вы можете создавать любые способы оплаты заказов. Логика оплаты (отправка покупателя на удалённый сервис, приём оплаты и т.п.) реализуется в классе, который вы укажете.<br/>Для методов оплаты параметр "класс" обязателен.';
$_lang['ms_delivery'] = 'Доставка';
$_lang['ms_deliveries'] = 'Варианты доставки';
$_lang['ms_deliveries_intro'] = 'Возможные варианты доставки. Логика рассчёта стоимости доставки в зависимости от расстояния и веса реализуется классом, который вы укажете в настройках.<br/>Если вы не укажете свой класс, рассчеты будут производиться алгоритмом по-умолчанию.';
$_lang['ms_statuses'] = 'Статусы заказа';
$_lang['ms_statuses_intro'] = 'Существует несколько обязательных статусов заказа: "новый", "оплачен", "отправлен" и "отменён". Их можно настраивать, но нельзя удалять, так как они необходимы для работы магазина. Вы можете указать свои статусы для расширенной логики работы с заказами.<br/>Статус может быть окончательным, это значит, что его нельзя переключить на другой, например "отправлен" и "отменён". Статус может быть зафиксирован, то есть, с него нельзя переключаться на более ранние статусы, например "оплачен" нельзя переключить на "новый".';
$_lang['ms_vendors'] = 'Производители товаров';
$_lang['ms_vendors_intro'] = 'Список возможных производителей товаров. То, что вы сюда добавите, можно выбрать в поле "vendor" товара.';
$_lang['ms_link'] = 'Связь товаров';
$_lang['ms_links'] = 'Связи товаров';
$_lang['ms_links_intro'] = 'Список возможных связей товаров друг с другом. Тип связи характеризует, как именно она будет работать, его нельзя создавать, можно только выбрать из списка.';
$_lang['ms_option'] = 'Свойство товаров';
$_lang['ms_options'] = 'Свойства товаров';
$_lang['ms_options_intro'] = 'Список возможных свойств товаров. Дерево категорий используется для фильтрации свойств выбранных категорий.<br/>Чтобы назначить категориям сразу несколько опций, выберите их через Ctrl(Cmd) или Shift.';
$_lang['ms_options_category_intro'] = 'Список возможных свойств товаров в данной категории.';
$_lang['ms_default_value'] = 'Значение по умолчанию';
$_lang['ms_customer'] = 'Покупатель';
$_lang['ms_all'] = 'Все';
$_lang['ms_type'] = 'Тип';

$_lang['ms_btn_create'] = 'Создать';
$_lang['ms_btn_copy'] = 'Скопировать';
$_lang['ms_btn_save'] = 'Сохранить';
$_lang['ms_btn_edit'] = 'Изменить';
$_lang['ms_btn_view'] = 'Просмотр';
$_lang['ms_btn_delete'] = 'Удалить';
$_lang['ms_btn_undelete'] = 'Восстановить';
$_lang['ms_btn_publish'] = 'Включить';
$_lang['ms_btn_unpublish'] = 'Отключить';
$_lang['ms_btn_cancel'] = 'Отмена';
$_lang['ms_btn_back'] = 'Назад (alt + &uarr;)';
$_lang['ms_btn_prev'] = 'Предыдущий товар (alt + &larr;)';
$_lang['ms_btn_next'] = 'Следующий товар (alt + &rarr;)';
$_lang['ms_btn_help'] = 'Помощь';
$_lang['ms_btn_duplicate'] = 'Сделать копию товара';
$_lang['ms_btn_addoption'] = 'Добавить';
$_lang['ms_btn_assign'] = 'Назначить';

$_lang['ms_actions'] = 'Действия';
$_lang['ms_search'] = 'Поиск';
$_lang['ms_search_clear'] = 'Очистить';

$_lang['ms_category'] = 'Категория товаров';
$_lang['ms_category_tree'] = 'Дерево категорий';
$_lang['ms_category_type'] = 'Категория товаров';
$_lang['ms_category_create'] = 'Добавить категорию';
$_lang['ms_category_create_here'] = 'Категорию с товарами';
$_lang['ms_category_manage'] = 'Управление товарами';
$_lang['ms_category_duplicate'] = 'Копировать категорию';
$_lang['ms_category_publish'] = 'Опубликовать категорию';
$_lang['ms_category_unpublish'] = 'Убрать с публикации';
$_lang['ms_category_delete'] = 'Удалить категорию';
$_lang['ms_category_undelete'] = 'Восстановить категорию';
$_lang['ms_category_view'] = 'Просмотреть на сайте';
$_lang['ms_category_new'] = 'Новая категория';
$_lang['ms_category_option_add'] = 'Добавить характеристику';
$_lang['ms_category_option_rank'] = 'Порядок сортировки';
$_lang['ms_category_show_nested'] = 'Показывать вложенные товары';

$_lang['ms_product'] = 'Товар магазина';
$_lang['ms_product_type'] = 'Товар магазина';
$_lang['ms_product_create_here'] = 'Товар магазина';
$_lang['ms_product_create'] = 'Добавить товар';

$_lang['ms_option_type'] = 'Тип свойства';

$_lang['ms_frontend_currency'] = 'руб.';
$_lang['ms_frontend_weight_unit'] = 'кг.';
$_lang['ms_frontend_count_unit'] = 'шт.';
$_lang['ms_frontend_add_to_cart'] = 'Добавить в корзину';
$_lang['ms_frontend_tags'] = 'Теги';
$_lang['ms_frontend_colors'] = 'Цвета';
$_lang['ms_frontend_color'] = 'Цвет';
$_lang['ms_frontend_sizes'] = 'Размеры';
$_lang['ms_frontend_size'] = 'Размер';
$_lang['ms_frontend_popular'] = 'Популярный товар';
$_lang['ms_frontend_favorite'] = 'Рекомендуем';
$_lang['ms_frontend_new'] = 'Новинка';
$_lang['ms_frontend_deliveries'] = 'Варианты доставки';
$_lang['ms_frontend_delivery'] = 'Доставка';
$_lang['ms_frontend_payments'] = 'Способы оплаты';
$_lang['ms_frontend_payment'] = 'Оплата';
$_lang['ms_frontend_delivery_select'] = 'Выберите доставку';
$_lang['ms_frontend_payment_select'] = 'Выберите оплату';
$_lang['ms_frontend_credentials'] = 'Данные получателя';
$_lang['ms_frontend_address'] = 'Адрес доставки';

$_lang['ms_frontend_comment'] = 'Комментарий';
$_lang['ms_frontend_receiver'] = 'Получатель';
$_lang['ms_frontend_email'] = 'Email';
$_lang['ms_frontend_phone'] = 'Телефон';
$_lang['ms_frontend_index'] = 'Почтовый индекс';
$_lang['ms_frontend_country'] = 'Страна';
$_lang['ms_frontend_region'] = 'Область';
$_lang['ms_frontend_city'] = 'Город';
$_lang['ms_frontend_street'] = 'Улица';
$_lang['ms_frontend_building'] = 'Дом';
$_lang['ms_frontend_room'] = 'Кв.';
$_lang['ms_frontend_entrance'] = 'Подъезд';
$_lang['ms_frontend_floor'] = 'Этаж';
$_lang['ms_frontend_text_address'] = 'Адрес одной строкой';

$_lang['ms_frontend_order_cost'] = 'Итого, с доставкой';
$_lang['ms_frontend_order_submit'] = 'Сделать заказ!';
$_lang['ms_frontend_order_cancel'] = 'Очистить форму';
$_lang['ms_frontend_order_success'] = 'Спасибо за оформление заказа <b>#[[+num]]</b> на нашем сайте <b>[[++site_name]]</b>!';

$_lang['ms_message_close_all'] = 'закрыть все';
$_lang['ms_err_unknown'] = 'Неизвестная ошибка';
$_lang['ms_err_ns'] = 'Это поле обязательно';
$_lang['ms_err_ae'] = 'Это поле должно быть уникально';
$_lang['ms_err_json'] = 'Это поле требует JSON строку';

$_lang['ms_err_user_nf'] = 'Пользователь не найден.';
$_lang['ms_err_order_nf'] = 'Заказ с таким идентификатором не найден.';
$_lang['ms_err_status_nf'] = 'Статус с таким идентификатором не найден.';
$_lang['ms_err_delivery_nf'] = 'Способ доставки с таким идентификатором не найден.';
$_lang['ms_err_payment_nf'] = 'Способ оплаты с таким идентификатором не найден.';
$_lang['ms_err_status_final'] = 'Установлен финальный статус. Его нельзя менять.';
$_lang['ms_err_status_fixed'] = 'Установлен фиксирующий статус. Вы не можете сменить его на более ранний.';
$_lang['ms_err_status_wrong'] = 'Неверный статус заказа.';
$_lang['ms_err_status_same'] = 'Этот статус уже установлен.';
$_lang['ms_err_register_globals'] = 'Ошибка: php параметр <b>register_globals</b> должен быть выключен.';
$_lang['ms_err_link_equal'] = 'Вы пытаетесь добавить товару ссылку на самого себя';
$_lang['ms_err_value_duplicate'] = 'Вы не ввели значение или ввели повтор.';

$_lang['ms_err_gallery_save'] = 'Не могу сохранить файл не был сохранён (см. системный журнал).';
$_lang['ms_err_gallery_ns'] = 'Передан пустой файл';
$_lang['ms_err_gallery_ext'] = 'Неверное расширение файла';
$_lang['ms_err_gallery_exists'] = 'Такое изображение уже есть в галерее товара.';
$_lang['ms_err_gallery_thumb'] = 'Не получилось сгенерировать превьюшки. Смотрите системный лог.';
$_lang['ms_err_wrong_image'] = 'Файл не является корректным изображением.';
$_lang['ms_err_gallery_is_not_msproduct'] = '[msGallery] Ресурс с id = [[+id]] не является товаром.';

$_lang['ms_email_subject_new_user'] = 'Вы сделали заказ #[[+num]] на сайте [[++site_name]]';
$_lang['ms_email_subject_new_manager'] = 'У вас новый заказ #[[+num]]';
$_lang['ms_email_subject_paid_user'] = 'Вы оплатили заказ #[[+num]]';
$_lang['ms_email_subject_paid_manager'] = 'Заказ #[[+num]] был оплачен';
$_lang['ms_email_subject_sent_user'] = 'Ваш заказ #[[+num]] был отправлен';
$_lang['ms_email_subject_cancelled_user'] = 'Ваш заказ #[[+num]] был отменён';

$_lang['ms_payment_link'] = 'Если вы случайно прервали процедуру оплаты, вы всегда можете <a href="[[+link]]" style="color:#348eda;">продолжить её по этой ссылке</a>.';

$_lang['ms_category_err_ns'] = 'Категория не выбрана';
$_lang['ms_option_err_ns'] = 'Свойство не выбрано';
$_lang['ms_option_err_nf'] = 'Свойство не найдено';
$_lang['ms_option_err_ae'] = 'Свойство уже существует';
$_lang['ms_option_err_save'] = 'Ошибка сохранения свойства';
$_lang['ms_option_err_reserved_key'] = 'Такой ключ опции использовать нельзя';
$_lang['ms_option_err_invalid_key'] = 'Неправильный ключ для свойства';
