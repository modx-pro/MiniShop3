<?php

/**
 * Settings Russian Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

$_lang['area_ms3_main'] = 'Основные настройки';
$_lang['area_ms3_category'] = 'Категория товаров';
$_lang['area_ms3_product'] = 'Товар';
$_lang['area_ms3_gallery'] = 'Галерея';
$_lang['area_ms3_cart'] = 'Корзина';
$_lang['area_ms3_order'] = 'Заказы';
$_lang['area_ms3_frontend'] = 'Сайт';
$_lang['area_ms3_payment'] = 'Платежи';
$_lang['area_ms3_import'] = 'Импорт';
$_lang['area_ms3_statuses'] = 'Статусы';

$_lang['setting_ms3_services'] = 'Службы магазина';
$_lang['setting_ms3_services_desc'] = 'Массив с зарегистрированными классами для корзины, заказа, доставки и оплаты. Используется сторонними дополнениями для загрузки своего функционала.';
$_lang['setting_ms3_plugins'] = 'Плагины магазина';
$_lang['setting_ms3_plugins_desc'] = 'Массив с зарегистрированными плагинами расширения объектов модели магазина: товаров, профилей покупателя и т.д.';
$_lang['setting_ms3_chunks_categories'] = 'Категории для списка чанков';
$_lang['setting_ms3_chunks_categories_desc'] = 'Список ID категорий через запятую  для списка чанков.';
$_lang['setting_ms3_tmp_storage'] = 'Хранилище корзины и временных полей заказа';
$_lang['setting_ms3_tmp_storage_desc'] = "
Для хранения корзины и временных полей заказа в сессии укажите <strong>session</strong><br>
Для хранения в базе данных укажите <strong>db</strong>";

$_lang['setting_ms3_category_grid_fields'] = 'Поля таблицы товаров';
$_lang['setting_ms3_category_grid_fields_desc'] = 'Список видимых полей таблицы с товарами категории, через запятую. Доступны: "id,menuindex,pagetitle,article,price,thumb,new,favorite,popular';
$_lang['setting_ms3_product_main_fields'] = 'Основные поля панели товара';
$_lang['setting_ms3_product_main_fields_desc'] = 'Список полей панели товара, через запятую. Например: "pagetitle,longtitle,content".';
$_lang['setting_ms3_product_extra_fields'] = 'Дополнительные поля товара';
$_lang['setting_ms3_product_extra_fields_desc'] = 'Список дополнительных полей товара, использующихся в магазине, через запятую. Например: "price,old_price,weight".';

$_lang['setting_mgr_tree_icon_mscategory'] = 'Иконка категории';
$_lang['setting_mgr_tree_icon_mscategory_desc'] = 'Иконка категории товаров MiniShop3 в дереве ресурсов';
$_lang['setting_mgr_tree_icon_msproduct'] = 'Иконка товара';
$_lang['setting_mgr_tree_icon_msproduct_desc'] = 'Иконка товара MiniShop3 в дереве ресурсов';

$_lang['setting_ms3_product_tab_extra'] = 'Вкладка свойств товара';
$_lang['setting_ms3_product_tab_extra_desc'] = 'Показывать вкладку свойств товара?';
$_lang['setting_ms3_product_tab_gallery'] = 'Вкладка галереи товара';
$_lang['setting_ms3_product_tab_gallery_desc'] = 'Показывать вкладку галереи товара?';
$_lang['setting_ms3_product_tab_links'] = 'Вкладка связей товара';
$_lang['setting_ms3_product_tab_links_desc'] = 'Показывать вкладку связей товара?';
$_lang['setting_ms3_product_tab_options'] = 'Вкладка опций товара';
$_lang['setting_ms3_product_tab_options_desc'] = 'Показывать вкладку опций товара?';
$_lang['setting_ms3_product_tab_categories'] = 'Вкладка категорий товара';
$_lang['setting_ms3_product_tab_categories_desc'] = 'Показывать вкладку категорий товара?';

$_lang['setting_ms3_category_show_comments'] = 'Показывать комментарии категории';
$_lang['setting_ms3_category_show_comments_desc'] = 'Показывать комментарии оставленные ко всем товарам категории, если установлен компонент "Tickets"';
$_lang['setting_ms3_category_show_nested_products'] = 'Показывать вложенные товары категории';
$_lang['setting_ms3_category_show_nested_products_desc'] = 'Если вы включаете эту опцию, то в категории будут показаны все вложенные товары. Они выделены другим цветом и у них есть имя родной категории под pagetitle.';
$_lang['setting_ms3_category_show_options'] = 'Показывать опции товаров категории';
$_lang['setting_ms3_category_show_options_desc'] = 'Показывать опции к товарам категории.';
$_lang['setting_ms3_category_remember_tabs'] = 'Запоминание вкладки категории';
$_lang['setting_ms3_category_remember_tabs_desc'] = 'Если включено, активная вкладка панели категории будет запоминаться и восстанавливаться при загрузке страницы.';
$_lang['setting_ms3_category_remember_grid'] = 'Запоминание таблицы категории';
$_lang['setting_ms3_category_remember_grid_desc'] = 'Если включено, состояние таблицы категории будет запоминаться и восстанавливаться при загрузке страницы, включая номер страницы и строку поиска.';
$_lang['setting_ms3_category_id_as_alias'] = 'Id категории как псевдоним';
$_lang['setting_ms3_category_id_as_alias_desc'] = 'Если включено, псевдонимы для дружественных имён категорий не будут генерироваться. Вместо этого будут подставляться их id.';
$_lang['setting_ms3_category_content_default'] = 'Содержимое категории по умолчанию';
$_lang['setting_ms3_category_content_default_desc'] = 'Здесь вы можете указать контент вновь создаваемой категории. По умолчанию установлен вывод дочерних товаров.';
$_lang['setting_ms3_product_show_comments'] = 'Показывать комментарии товара';
$_lang['setting_ms3_product_show_comments_desc'] = 'Показывать комментарии оставленные к товару, если установлен компонент "Tickets"';
$_lang['setting_ms3_template_category_default'] = 'Шаблон по умолчанию для новых категорий';
$_lang['setting_ms3_template_category_default_desc'] = 'Выберите шаблон, который будет установлен по умолчанию при создании категории.';
$_lang['setting_ms3_template_product_default'] = 'Шаблон по умолчанию для новых товаров';
$_lang['setting_ms3_template_product_default_desc'] = 'Выберите шаблон, который будет установлен по умолчанию при создании товара.';
$_lang['setting_ms3_product_show_in_tree_default'] = 'Показывать в дереве по умолчанию';
$_lang['setting_ms3_product_show_in_tree_default_desc'] = 'Включите эту опцию, чтобы все создаваемые товары были видны в дереве ресурсов.';
$_lang['setting_ms3_product_source_default'] = 'Источник файлов по умолчанию';
$_lang['setting_ms3_product_source_default_desc'] = 'Источник файлов для галереи изображений товара по умолчанию.';
$_lang['setting_ms3_product_vertical_tabs'] = 'Вертикальные табы на странице товара';
$_lang['setting_ms3_product_vertical_tabs_desc'] = 'Как показывать страницу товара? Отключение этой опции позволяет уместить страницу товара на экранах с небольшой горизонталью. Не рекомендуется.';
$_lang['setting_ms3_product_remember_tabs'] = 'Запоминание вкладки товара';
$_lang['setting_ms3_product_remember_tabs_desc'] = 'Если включено, активная вкладка панели товара будет запоминаться и восстанавливаться при загрузке страницы.';

$_lang['setting_ms3_product_thumbnail_size'] = 'Размер превью по умолчанию';
$_lang['setting_ms3_product_thumbnail_size_desc'] = 'Здесь вы можете указать размер заранее уменьшенной копии изображения для вставки поля "thumb" товара. Конечно, этот размер должен существовать и в настройках источника медиа, чтобы генерировались такие превью. В противном случае вы получите логотип MiniShop3 вместо изображения товара в админке.';

$_lang['setting_ms3_product_thumbnail_default'] = 'Файл превью по умолчанию';
$_lang['setting_ms3_product_thumbnail_default_desc'] = 'Здесь вы можете указать путь к файлу превью по умолчанию для вставки поля "thumb" товара. По умолчанию вы получаете логотип MiniShop3.';
$_lang['setting_ms3_product_id_as_alias'] = 'Id товара как псевдоним';
$_lang['setting_ms3_product_id_as_alias_desc'] = 'Если включено, псевдонимы для дружественных имён товаров не будут генерироваться. Вместо этого будут подставляться их id.';

$_lang['setting_ms3_cart_handler_class'] = 'Класс обработчик корзины';
$_lang['setting_ms3_cart_handler_class_desc'] = 'Имя класса, который реализует логику работы с корзиной.';
$_lang['setting_ms3_cart_context'] = 'Использовать единую корзину для всех контекстов?';
$_lang['setting_ms3_cart_context_desc'] = 'Если включено, то используется общая корзина для всех контекстов. Если выключено - то у каждого контекста используется своя корзина.';
$_lang['setting_ms3_order_handler_class'] = 'Класс обработчик заказа';
$_lang['setting_ms3_order_handler_class_desc'] = 'Имя класса, который реализует логику оформления заказа.';
$_lang['setting_ms3_cart_max_count'] = 'Максимальное количество товаров в корзине';
$_lang['setting_ms3_cart_max_count_desc'] = 'По умолчанию 1000. При превышении этого значения будет выведено уведомление.';
$_lang['setting_ms3_order_tv_list'] = 'Список TV через запятую, которые попадут в письмо';
$_lang['setting_ms3_order_tv_list_desc'] = 'Введите список TV товара через запятую, для использования их в чанке писем';
$_lang['setting_ms3_order_user_groups'] = 'Группы регистрации покупателей';
$_lang['setting_ms3_order_user_groups_desc'] = 'Список групп, через запятую, в которые вы хотите добавлять новых покупателей при оформлении заказа.';
$_lang['setting_ms3_email_manager'] = 'Почтовые адреса менеджеров';
$_lang['setting_ms3_email_manager_desc'] = 'Список почтовых ящиков менеджеров, через запятую, на которые отправлять уведомления об изменении статуса заказа.';
$_lang['setting_ms3_date_format'] = 'Формат даты';
$_lang['setting_ms3_date_format_desc'] = 'Укажите формат дат MiniShop3, используя синтаксис php функции date(). По умолчанию формат "d.m.y H:M".';
$_lang['setting_ms3_price_format'] = 'Формат цен';
$_lang['setting_ms3_price_format_desc'] = 'Укажите, как нужно форматировать цены товаров функцией number_format(). Используется JSON строка с массивом для передачи 3х параметров: количество десятичных, разделитель десятичных и разделитель тысяч. По умолчанию формат [2,"."," "], что превращает "15336.6" в "15 336.60"';
$_lang['setting_ms3_price_format_no_zeros'] = 'Убирать лишние нули в ценах';
$_lang['setting_ms3_price_format_no_zeros_desc'] = 'По умолчанию, цены товаров выводятся с двумя десятичными: "15.20". Если эта опция включена, лишние нули в конце цены убираются и вы получите "15.2".';
$_lang['setting_ms3_weight_format'] = 'Формат веса';
$_lang['setting_ms3_weight_format_desc'] = 'Укажите, как нужно форматировать вес товаров функцией number_format(). Используется JSON строка с массивом для передачи 3х параметров: количество десятичных, разделитель десятичных и разделитель тысяч. По умолчанию формат [3,"."," "], что превращает "141.3" в "141.300"';
$_lang['setting_ms3_weight_format_no_zeros'] = 'Убирать лишние нули у веса';
$_lang['setting_ms3_weight_format_no_zeros_desc'] = 'По умолчанию, вес товаров выводятся с тремя десятичными: "15.250". Если эта опция включена, лишние нули в конце веса убираются и вы получите "15.25".';
$_lang['setting_ms3_price_snippet'] = 'Модификатор цены';
$_lang['setting_ms3_price_snippet_desc'] = 'Здесь вы можете указать имя сниппета для модификации цены при выводе на сайте и добавлении в корзину. Он должен принимать объект "$product" и возвращать число.';
$_lang['setting_ms3_weight_snippet'] = 'Модификатор веса';
$_lang['setting_ms3_weight_snippet_desc'] = 'Здесь вы можете указать имя сниппета для модификации веса товара при выводе на сайте и добавлении в корзину. Он должен принимать объект "$product" и возвращать число.';
$_lang['setting_ms3_token_name'] = 'Имя токена';
$_lang['setting_ms3_token_name_desc'] = 'Имя токена, используемого для идентификации посетителя. По умолчанию <strong>ms3_token</strong>';
$_lang['setting_ms3_register_global_config'] = 'Регистрировать глобальный конфиг настроек в DOM';
$_lang['setting_ms3_register_global_config_desc'] = '';
$_lang['setting_ms3_frontend_assets'] = 'Регистрировать глобальный конфиг настроек в DOM';
$_lang['setting_ms3_frontend_assets_desc'] = '';


$_lang['setting_ms3_order_format_num'] = 'Формат нумерации заказа';
$_lang['setting_ms3_order_format_num_desc'] = 'Формат нумерации заказа. Доступные значения в формате PHP date()';
$_lang['setting_ms3_order_format_num_separator'] = 'Разделитель для нумерации заказа';
$_lang['setting_ms3_order_format_num_separator_desc'] = 'Разделитель для нумерации заказа. Доступные значения: "/", "," и "-"';
$_lang['setting_ms3_order_grid_fields'] = 'Поля таблицы заказов';
$_lang['setting_ms3_order_grid_fields_desc'] = 'Список полей, которые будут показаны в таблице заказов. Доступны: "createdon,updatedon,num,cost,cart_cost,delivery_cost,weight,status,delivery,payment,customer,receiver".';
$_lang['setting_ms3_order_address_fields'] = 'Поля адреса доставки';
$_lang['setting_ms3_order_address_fields_desc'] = 'Список полей доставки, которые будут показаны на третьей вкладке карточки заказа. Доступны: "receiver,phone,index,country,region,metro,building,city,street,room,entrance,floor,text_address". Если параметр пуст, вкладка будет скрыта.';
$_lang['setting_ms3_order_product_fields'] = 'Поля таблицы покупок';
$_lang['setting_ms3_order_product_fields_desc'] = 'Список полей таблицы заказанных товаров. Доступны: "count,price,weight,cost,options". Поля товара указываются с префиксом "product_", например "product_pagetitle,product_article". Дополнительно можно указывать значения из поля options с префиксом "option_", например: "option_color,option_size".';
$_lang['setting_ms3_order_product_options'] = 'Поля опций продукта в заказе';
$_lang['setting_ms3_order_product_options_desc'] = 'Перечень редактируемых опций товара в окне заказа. По умолчанию color, size';
$_lang['setting_ms3_status_draft'] = 'ID статуса заказа Черновик';
$_lang['setting_ms3_status_draft_desc'] = 'Какой статус нужно устанавливать для заказа-черновика';
$_lang['setting_ms3_status_new'] = 'ID первоначального статуса заказа';
$_lang['setting_ms3_status_new_desc'] = 'Какой статус нужно устанавливать для нового совершенного заказа';
$_lang['setting_ms3_status_paid'] = 'ID статуса оплаченного заказа';
$_lang['setting_ms3_status_paid_desc'] = 'Какой статус нужно устанавливать после оплаты заказа';
$_lang['setting_ms3_status_canceled'] = 'ID статуса отмены заказа';
$_lang['setting_ms3_status_canceled_desc'] = 'Какой статус нужно устанавливать при отмене заказа';
$_lang['setting_ms3_status_for_stat'] = 'ID статусов для статистики';
$_lang['setting_ms3_status_for_stat_desc'] = 'Статусы через запятую, для построения статистики ВЫПОЛНЕННЫХ заказов';
$_lang['setting_ms3_use_scheduler'] = 'Использовать менеджер очередей';
$_lang['setting_ms3_use_scheduler_desc'] = 'Перед использованием убедитесь, что у вас установлен компонент Scheduler';
$_lang['setting_ms3_utility_import_fields'] = 'Список полей для импорта';
$_lang['setting_ms3_utility_import_fields_delimiter'] = 'Разделитель колонок в файле импорта';

$_lang['ms3_source_thumbnails_desc'] = 'Закодированный в JSON массив с параметрами генерации уменьшенных копий изображений.';
$_lang['ms3_source_maxUploadWidth_desc'] = 'Максимальная ширина изображения для загрузки. Всё, что больше, будет ужато до этого значения.';
$_lang['ms3_source_maxUploadHeight_desc'] = 'Максимальная высота изображения для загрузки. Всё, что больше, будет ужато до этого значения.';
$_lang['ms3_source_maxUploadSize_desc'] = 'Максимальный размер загружаемых изображений (в байтах).';
$_lang['ms3_source_imageNameType_desc'] = 'Этот параметр указывает, как нужно переименовать файл при загрузке. Hash - это генерация уникального имени, в зависимости от содержимого файла. Friendly - генерация имени по алгоритму дружественных url страниц сайта (они управляются системными настройками).';
$_lang['setting_ms3_utility_import_fields'] = 'Список полей для импорта';
$_lang['setting_ms3_utility_import_fields_delimiter'] = 'Разделитель колонок в файле импорта';
