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
$_lang['area_ms3_customers'] = 'Клиенты';
$_lang['area_ms3_security'] = 'Безопасность';
$_lang['area_ms3_api'] = 'API';
$_lang['area_ms3_notifications'] = 'Уведомления';

$_lang['setting_ms3_chunks_categories'] = 'Категории для списка чанков';
$_lang['setting_ms3_chunks_categories_desc'] = 'Список ID категорий через запятую  для списка чанков.';
$_lang['setting_ms3_tmp_storage'] = 'Хранилище корзины и временных полей заказа';
$_lang['setting_ms3_tmp_storage_desc'] = "
Для хранения корзины и временных полей заказа в сессии укажите <strong>session</strong><br>
Для хранения в базе данных укажите <strong>db</strong>";

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
$_lang['setting_ms3_category_remember_grid'] = 'Запоминание таблицы категории';
$_lang['setting_ms3_category_remember_grid_desc'] = 'Если включено, состояние таблицы категории будет запоминаться и восстанавливаться при загрузке страницы, включая номер страницы и строку поиска.';
$_lang['setting_ms3_category_id_as_alias'] = 'Id категории как псевдоним';
$_lang['setting_ms3_category_id_as_alias_desc'] = 'Если включено, псевдонимы для дружественных имён категорий не будут генерироваться. Вместо этого будут подставляться их id.';
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

$_lang['setting_ms3_cart_context'] = 'Использовать единую корзину для всех контекстов?';
$_lang['setting_ms3_cart_context_desc'] = 'Если включено, то используется общая корзина для всех контекстов. Если выключено - то у каждого контекста используется своя корзина.';
$_lang['setting_ms3_cart_max_count'] = 'Максимальное количество товаров в корзине';
$_lang['setting_ms3_cart_max_count_desc'] = 'По умолчанию 1000. При превышении этого значения будет выведено уведомление.';
$_lang['setting_ms3_cart_page_id'] = 'ID страницы корзины';
$_lang['setting_ms3_cart_page_id_desc'] = 'ID страницы корзины. Используется для ссылки "Перейти в корзину" в миникорзине.';
$_lang['setting_ms3_order_page_id'] = 'ID страницы оформления заказа';
$_lang['setting_ms3_order_page_id_desc'] = 'ID страницы оформления заказа. Используется для ссылки "Оформить заказ" в корзине.';
$_lang['setting_ms3_order_user_groups'] = 'Группы регистрации покупателей';
$_lang['setting_ms3_order_user_groups_desc'] = 'Список групп, через запятую, в которые вы хотите добавлять новых покупателей при оформлении заказа.';
$_lang['setting_ms3_order_redirect_thanks_id'] = 'ID страницы "Спасибо за заказ"';
$_lang['setting_ms3_order_redirect_thanks_id_desc'] = 'ID страницы, на которую произойдет редирект, после оформления заказа.';
$_lang['setting_ms3_order_register_user_on_submit'] = 'Создавать системного пользователя при заказе';
$_lang['setting_ms3_order_register_user_on_submit_desc'] = 'Включая эту настройку, вы будете создавать при заказе нового системного пользователя, которому можно назначить права и поместить в определенную группу пользователей. По умолчанию отключено.';
$_lang['setting_ms3_order_show_drafts'] = 'Показывать черновики в списке заказов';
$_lang['setting_ms3_order_show_drafts_desc'] = 'Выберите Нет, если не хотите видеть черновики в таблице заказов.';
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
$_lang['setting_ms3_register_global_config_desc'] = 'Регистрирует в DOM json массив важных настроек для использования скриптами';
$_lang['setting_ms3_frontend_assets'] = 'Перечень подключаемых CSS/JS файлов';
$_lang['setting_ms3_frontend_assets_desc'] = 'CSS файлы будут подключены в head, JS файлы будут подключены в конце html, с атрибутом defer';


$_lang['setting_ms3_order_format_num'] = 'Формат нумерации заказа';
$_lang['setting_ms3_order_format_num_desc'] = 'Формат нумерации заказа. Доступные значения в формате PHP date()';
$_lang['setting_ms3_order_format_num_separator'] = 'Разделитель для нумерации заказа';
$_lang['setting_ms3_order_format_num_separator_desc'] = 'Разделитель для нумерации заказа. Доступные значения: "/", "," и "-"';
$_lang['setting_ms3_delete_drafts_after'] = 'Удалять черновики заказов после';
$_lang['setting_ms3_delete_drafts_after_desc'] = 'Укажите strtotime()-совместимую строку (например "-1 year" или "-2 weeks") для автоматического удаления устаревших черновиков заказов. Требуется компонент Scheduler с задачей minishop3:cleanupDrafts.';
$_lang['setting_ms3_order_log_actions'] = 'Логируемые действия с заказом';
$_lang['setting_ms3_order_log_actions_desc'] = 'Типы действий для записи в историю заказа. Доступны: status (смена статуса), products (изменение товаров), field (изменение полей), address (изменение адреса), payment (платежи). Через запятую. Значение * — логировать всё. Пусто — отключить логирование.';
$_lang['setting_ms3_status_draft'] = 'ID статуса заказа Черновик';
$_lang['setting_ms3_status_draft_desc'] = 'Какой статус нужно устанавливать для заказа-черновика';
$_lang['setting_ms3_status_new'] = 'ID первоначального статуса заказа';
$_lang['setting_ms3_status_new_desc'] = 'Какой статус нужно устанавливать для нового совершенного заказа';
$_lang['setting_ms3_status_paid'] = 'ID статуса оплаченного заказа';
$_lang['setting_ms3_status_paid_desc'] = 'Какой статус нужно устанавливать после оплаты заказа';
$_lang['setting_ms3_status_canceled'] = 'ID статуса отмены заказа';
$_lang['setting_ms3_status_canceled_desc'] = 'Какой статус нужно устанавливать при отмене заказа';
$_lang['setting_ms3_customer_cancel_allowed_statuses'] = 'Статусы, из которых покупатель может отменить заказ';
$_lang['setting_ms3_customer_cancel_allowed_statuses_desc'] = 'ID статусов через запятую. По умолчанию: «Новый» и «Оплачен» (2,3). Пусто — использовать ms3_status_new и ms3_status_paid.';
$_lang['setting_ms3_status_for_stat'] = 'ID статусов для статистики';
$_lang['setting_ms3_status_for_stat_desc'] = 'Статусы через запятую, для построения статистики ВЫПОЛНЕННЫХ заказов';
$_lang['setting_ms3_use_scheduler'] = 'Использовать менеджер очередей';
$_lang['setting_ms3_use_scheduler_desc'] = 'Перед использованием убедитесь, что у вас установлен компонент Scheduler';
$_lang['setting_ms3_utility_import_fields'] = 'Список полей для импорта';
$_lang['setting_ms3_utility_import_fields_delimiter'] = 'Разделитель колонок в файле импорта';
$_lang['setting_ms3_import_sync_limit'] = 'Лимит синхронного импорта';
$_lang['setting_ms3_import_sync_limit_desc'] = 'Максимальное количество строк для синхронного импорта. При превышении рекомендуется использовать фоновую обработку (Scheduler).';
$_lang['setting_ms3_import_preview_rows'] = 'Строк для предпросмотра';
$_lang['setting_ms3_import_preview_rows_desc'] = 'Количество строк CSV для предпросмотра при настройке маппинга.';



$_lang['ms3_source_thumbnails_desc'] = 'Закодированный в JSON массив с параметрами генерации уменьшенных копий изображений.';
$_lang['ms3_source_max_upload_width_desc'] = 'Максимальная ширина изображения для загрузки. Всё, что больше, будет ужато до этого значения.';
$_lang['ms3_source_max_upload_height_desc'] = 'Максимальная высота изображения для загрузки. Всё, что больше, будет ужато до этого значения.';
$_lang['ms3_source_max_upload_size_desc'] = 'Максимальный размер загружаемых изображений (в байтах).';
$_lang['ms3_source_image_name_type_desc'] = 'Этот параметр указывает, как нужно переименовать файл при загрузке. Hash - это генерация уникального имени, в зависимости от содержимого файла. Friendly - генерация имени по алгоритму дружественных url страниц сайта (они управляются системными настройками).';

// Token Security Settings
$_lang['area_ms3_security'] = 'Безопасность';
$_lang['setting_ms3_customer_token_ttl'] = 'Время жизни токена покупателя (TTL)';
$_lang['setting_ms3_customer_token_ttl_desc'] = 'Время в секундах, в течение которого токен покупателя остается действительным. По умолчанию 86400 (24 часа). После истечения срока пользователь получит новый токен.';
$_lang['setting_ms3_snippet_token_secret'] = 'Секретный ключ для токенов сниппетов';
$_lang['setting_ms3_snippet_token_secret_desc'] = 'Криптографически стойкий секретный ключ для генерации токенов сниппетов. Генерируется автоматически при первом запуске. НЕ изменяйте это значение без необходимости!';
$_lang['setting_ms3_snippet_cache_ttl'] = 'Время кеширования данных сниппетов (TTL)';
$_lang['setting_ms3_snippet_cache_ttl_desc'] = 'Время в секундах, в течение которого параметры сниппетов хранятся в кеше. По умолчанию 3600 (1 час). Используется для оптимизации производительности корзины.';
$_lang['setting_ms3_customer_api_token_ttl'] = 'Время жизни API токена клиента (TTL)';
$_lang['setting_ms3_customer_api_token_ttl_desc'] = 'Время в секундах, в течение которого API токен клиента остается действительным. По умолчанию 86400 (24 часа).';
$_lang['setting_ms3_password_reset_token_ttl'] = 'Время жизни токена сброса пароля (TTL)';
$_lang['setting_ms3_password_reset_token_ttl_desc'] = 'Время в секундах, в течение которого ссылка для сброса пароля остается действительной. По умолчанию 3600 (1 час).';
$_lang['setting_ms3_email_verification_token_ttl'] = 'Время жизни токена верификации email (TTL)';
$_lang['setting_ms3_email_verification_token_ttl_desc'] = 'Время в секундах, в течение которого ссылка для верификации email остается действительной. По умолчанию 86400 (24 часа).';
$_lang['setting_ms3_email_verification_url'] = 'Свой URL подтверждения email (необязательно)';
$_lang['setting_ms3_email_verification_url_desc'] = 'Если пусто, в письме подставляется ссылка на Web API (api.php), маршрут верификации. Для своей страницы укажите полный URL и плейсхолдер [[+token]] или {token} для подстановки токена.';
$_lang['setting_ms3_email_verification_success_url'] = 'URL редиректа после успешной верификации email (необязательно)';
$_lang['setting_ms3_email_verification_success_url_desc'] = 'Используется при переходе по ссылке из письма (параметр html=1). Если пусто — берётся site_url; к URL добавляется параметр ms3_email_verified=1.';
$_lang['setting_ms3_payment_secret'] = 'Секретный ключ для платежей';
$_lang['setting_ms3_payment_secret_desc'] = 'Секретный ключ для генерации подписей платежных уведомлений. Рекомендуется установить уникальное значение для повышения безопасности.';

// Currency and Formatting Settings
$_lang['setting_ms3_currency_symbol'] = 'Символ валюты';
$_lang['setting_ms3_currency_symbol_desc'] = 'Символ валюты для отображения цен. По умолчанию "₽" (рубль). Примеры: $, €, £, ₽, ₴, ¥, ₸.';
$_lang['setting_ms3_currency_position'] = 'Позиция символа валюты';
$_lang['setting_ms3_currency_position_desc'] = 'Где показывать символ валюты относительно цены. Допустимые значения: "before" (до цены: $ 100) или "after" (после цены: 100 ₽). По умолчанию "after".';
$_lang['setting_ms3_weight_unit'] = 'Единица измерения веса';
$_lang['setting_ms3_weight_unit_desc'] = 'Обозначение единицы измерения веса для отображения рядом со значением. Например: kg, г, кг, lbs. По умолчанию "kg".';

// Customer Authentication & Registration
$_lang['setting_ms3_customer_login_page_id'] = 'ID страницы входа';
$_lang['setting_ms3_customer_login_page_id_desc'] = 'ID страницы с формой входа клиента. Используется для редиректа неавторизованных пользователей.';
$_lang['setting_ms3_customer_register_page_id'] = 'ID страницы регистрации';
$_lang['setting_ms3_customer_register_page_id_desc'] = 'ID страницы с формой регистрации клиента.';
$_lang['setting_ms3_customer_auto_register_on_order'] = 'Автоматическая регистрация при оформлении заказа';
$_lang['setting_ms3_customer_auto_register_on_order_desc'] = 'Автоматически регистрировать клиента с паролем при оформлении заказа, если такого email нет в системе. Пароль генерируется автоматически и отправляется на email.';
$_lang['setting_ms3_customer_auto_login_on_order'] = 'Автоматический вход после заказа';
$_lang['setting_ms3_customer_auto_login_on_order_desc'] = 'Автоматически авторизовать клиента после успешного оформления заказа.';
$_lang['setting_ms3_customer_require_privacy_consent'] = 'Требовать согласие на обработку данных';
$_lang['setting_ms3_customer_require_privacy_consent_desc'] = 'Требовать согласие на обработку персональных данных при регистрации (GDPR).';
$_lang['setting_ms3_customer_auto_login_after_register'] = 'Автоматический вход после регистрации';
$_lang['setting_ms3_customer_auto_login_after_register_desc'] = 'Автоматически авторизовать клиента сразу после успешной регистрации.';
$_lang['setting_ms3_customer_require_email_verification'] = 'Требовать верификацию email';
$_lang['setting_ms3_customer_require_email_verification_desc'] = 'Требовать подтверждение email адреса после регистрации. Клиент получит письмо со ссылкой для верификации.';
$_lang['setting_ms3_customer_send_welcome_email'] = 'Отправлять приветственное письмо';
$_lang['setting_ms3_customer_send_welcome_email_desc'] = 'Отправлять приветственное письмо с паролем при автоматической регистрации через заказ.';
$_lang['setting_ms3_customer_redirect_after_login'] = 'Страница редиректа после входа/регистрации';
$_lang['setting_ms3_customer_redirect_after_login_desc'] = 'ID страницы, на которую будет перенаправлен клиент после успешного входа или регистрации. 0 = остаться на текущей странице (перезагрузка).';
$_lang['setting_ms3_customer_profile_page_id'] = 'ID страницы профиля клиента';
$_lang['setting_ms3_customer_profile_page_id_desc'] = 'ID страницы личного кабинета с профилем клиента. Используется для ссылок в навигации.';
$_lang['setting_ms3_customer_addresses_page_id'] = 'ID страницы адресов клиента';
$_lang['setting_ms3_customer_addresses_page_id_desc'] = 'ID страницы управления адресами доставки. Используется для ссылок в навигации.';
$_lang['setting_ms3_customer_orders_page_id'] = 'ID страницы заказов клиента';
$_lang['setting_ms3_customer_orders_page_id_desc'] = 'ID страницы истории заказов клиента. Используется для ссылок в навигации.';

// Customer Sync with modUser
$_lang['setting_ms3_customer_sync_enabled'] = 'Включить синхронизацию с modUser';
$_lang['setting_ms3_customer_sync_enabled_desc'] = 'Автоматически создавать/обновлять записи msCustomer при работе с modUser (через плагин msCustomerSync). Позволяет использовать единую базу клиентов и пользователей MODX.';
$_lang['setting_ms3_customer_sync_create_moduser'] = 'Создавать modUser при регистрации клиента';
$_lang['setting_ms3_customer_sync_create_moduser_desc'] = 'Автоматически создавать пользователя MODX (modUser) при регистрации msCustomer. Требует включенной синхронизации.';
$_lang['setting_ms3_customer_sync_user_group'] = 'Группа пользователей для новых modUser';
$_lang['setting_ms3_customer_sync_user_group_desc'] = 'ID группы пользователей MODX, в которую будут автоматически добавляться новые пользователи при создании из msCustomer. 0 = не добавлять в группу.';
$_lang['setting_ms3_customer_duplicate_fields'] = 'Поля для проверки дубликатов клиентов';
$_lang['setting_ms3_customer_duplicate_fields_desc'] = 'JSON-массив полей для проверки дубликатов при создании клиента. По умолчанию ["email", "phone"]. Проверка выполняется по логике OR (совпадение любого поля).';

// Login Security
$_lang['setting_ms3_customer_max_login_attempts'] = 'Максимум попыток входа';
$_lang['setting_ms3_customer_max_login_attempts_desc'] = 'Максимальное количество неудачных попыток входа перед блокировкой. По умолчанию 5.';
$_lang['setting_ms3_customer_block_duration'] = 'Длительность блокировки (сек)';
$_lang['setting_ms3_customer_block_duration_desc'] = 'Время блокировки в секундах после превышения лимита попыток входа. По умолчанию 300 (5 минут).';

// Password Requirements
$_lang['setting_ms3_password_min_length'] = 'Минимальная длина пароля';
$_lang['setting_ms3_password_min_length_desc'] = 'Минимальная длина пароля в символах. По умолчанию 8.';
$_lang['setting_ms3_password_require_uppercase'] = 'Требовать заглавные буквы';
$_lang['setting_ms3_password_require_uppercase_desc'] = 'Пароль должен содержать хотя бы одну заглавную букву (A-Z).';
$_lang['setting_ms3_password_require_number'] = 'Требовать цифры';
$_lang['setting_ms3_password_require_number_desc'] = 'Пароль должен содержать хотя бы одну цифру (0-9).';
$_lang['setting_ms3_password_require_special'] = 'Требовать спецсимволы';
$_lang['setting_ms3_password_require_special_desc'] = 'Пароль должен содержать хотя бы один специальный символ (!@#$%^&* и т.д.).';

// Order Settings
$_lang['setting_ms3_order_success_page_id'] = 'ID страницы успешной оплаты';
$_lang['setting_ms3_order_success_page_id_desc'] = 'ID страницы, на которую перенаправляется клиент после успешной оплаты заказа. 0 = редирект на страницу заказа.';

// Import Settings
$_lang['setting_ms3_import_upload_path'] = 'Путь для загрузки файлов импорта';
$_lang['setting_ms3_import_upload_path_desc'] = 'Относительный путь от MODX_BASE_PATH для загрузки CSV файлов импорта. По умолчанию "assets/import/".';

// API Settings
$_lang['setting_ms3_api_debug'] = 'Режим отладки API';
$_lang['setting_ms3_api_debug_desc'] = 'Включает расширенное логирование API запросов и ответов для отладки. Не рекомендуется на продакшене.';
$_lang['setting_ms3_cors_allowed_origins'] = 'Разрешённые CORS origins';
$_lang['setting_ms3_cors_allowed_origins_desc'] = 'Список доменов, которым разрешены кросс-доменные запросы к API. Используйте "*" для разрешения всех или укажите домены через запятую.';
$_lang['setting_ms3_rate_limit_max_attempts'] = 'Лимит запросов API';
$_lang['setting_ms3_rate_limit_max_attempts_desc'] = 'Максимальное количество API запросов за период. По умолчанию 60.';
$_lang['setting_ms3_rate_limit_decay_seconds'] = 'Период лимита запросов (сек)';
$_lang['setting_ms3_rate_limit_decay_seconds_desc'] = 'Временное окно в секундах для подсчёта лимита запросов. По умолчанию 60 секунд.';

// Notifications
$_lang['setting_ms3_telegram_bot_token'] = 'Токен Telegram бота';
$_lang['setting_ms3_telegram_bot_token_desc'] = 'Токен бота для отправки уведомлений в Telegram. Получите у @BotFather в Telegram.';
$_lang['setting_ms3_telegram_manager'] = 'Telegram chat ID менеджеров';
$_lang['setting_ms3_telegram_manager_desc'] = 'Список chat ID менеджеров через запятую для отправки уведомлений о заказах в Telegram. Узнать свой chat ID можно у бота @userinfobot.';

