# CHANGELOG - История изменений MiniShop3

Этот файл содержит хронологическую историю всех значительных изменений в проекте.

## Навигация

- **Текущий месяц:** [Март 2026](#март-2026) (ниже)
- **Предыдущий месяц:** [Февраль 2026](#февраль-2026) (ниже)
- **Ещё раньше:** [Январь 2026](#январь-2026) (ниже)
- **Архив по месяцам:**
  - [Декабрь 2025](changelogs/2025-12.md)
  - [Ноябрь 2025](changelogs/2025-11.md)
  - [Октябрь 2025](changelogs/2025-10.md)
  - [Архив (2024 и ранее)](changelogs/archive.md)

---

## Март 2026

### 🚀 Версия 1.8.0-beta1

**Тип релиза:** MINOR (beta) — Order Tabs Registry, модульные роуты аддонов, баг-фиксы

---

#### ✨ Добавлено

**MS3OrderTabsRegistry — кастомные вкладки в окне заказа (#166, #167):**
- `window.MS3OrderTabsRegistry.register()` — регистрация Vue и ExtJS вкладок на странице заказа без правки ядра
- Pre-mount очередь: плагины через `msOnManagerCustomCssJs` могут регистрировать табы до маунта Vue
- Общие пропсы для плагин-табов: `orderId`, `order`, `config`, `isCreateMode`
- `hideOnCreate` — скрытие вкладки в режиме создания заказа
- Защита reserved keys (info, products, address, history), валидация конфига, snapshot очереди
- Ленивый маунт ExtJS-панелей при первом открытии вкладки, cleanup в `onBeforeUnmount`
- Утилита `orderPluginTab.js` — валидация и нормализация конфига

**Модульная регистрация роутов для аддонов — `core/config/ms3.routes.d/` (#169):**
- Загрузка фрагментов `*.php` из `ms3.routes.d/web/` и `ms3.routes.d/manager/` (алфавитный порядок) после системных и custom-файлов, до `build()`; переопределение по ключу `METHOD:PATTERN`
- Метод `Router::loadRoutesFromDirectory()`, путь `Router::coreAddonRoutesDirectory('manager'|'web')` с проверкой аргумента; `api.php` и `Processors\Api\Index` грузят web-фрагменты; `Processors\Api\Router` (встроенная админка) — только manager-фрагменты
- Resolver создаёт каталоги при установке; примеры `example-addon.php.dist` в компоненте для копирования в `core/config/`

#### 🐛 Исправлено

- **Сниппет `ms3_cart` (#197):** после `msOnGetStatusCart` итоги в чанке/`return=data` выравниваются со всеми числовыми полями статуса (`total_cost`, `total_count`, `total_weight`, `total_discount`, `total_positions`), в том числе на ранних выходах при пустой корзине; `cost_formatted`/`weight_formatted` считаются от финального `$total`; без мутации `$outputData` после сборки
- **Manager API затирал `msOrder.properties` данными адреса (#191):** `array_merge($order->toArray(), $address->toArray())` перезаписывал `properties` заказа значением `null` из `msOrderAddress` — выделен `mergeAddressIntoOrderData()` с исключением конфликтующих полей
- **В форме заказа manager UI показывались raw lexicon keys в списках статусов, оплат и доставок (#193):** `GET /api/mgr/model-fields/visible/msOrder` загружал только `minishop3:vue`, из-за чего `comboOptions` не переводили значения из `minishop3:default` и `minishop3:manager`; дополнительно исправлены order-form dropdown routes для статусов и активных доставок
- **Пустая строка в decimal/int Extra Fields ломала сохранение товара (#170):** пустое значение кастомного поля (например `wholesale_price`) вызывало MySQL ошибку `Incorrect decimal value`, `save()` возвращал `false` и категории/опции/ссылки молча не сохранялись — хардкод каста `price`/`old_price`/`weight` заменён на универсальный цикл по `_fieldMeta` для всех `float`, `integer` и `boolean` полей
- **Чекбокс «Скрыть дочерние ресурсы» не сохранялся в категориях (#161, #160):** `hide_children_in_tree` не обрабатывался в `handleCheckBoxes()` процессоров `Category/Update` и `Category/Create` — unchecked-состояние не передавалось в POST и значение сбрасывалось
- **`publish_document` передавался как bool вместо int в контроллерах (#160):** `canPublish` не приводился к `(int)` в массиве JS-конфига — MODX JS использует строгое сравнение `=== 1`, из-за чего флаг мог не срабатывать. Исправлено во всех 4 контроллерах
- **Кнопки «Дублировать» и «Опубликовать» не отображались в гриде товаров категории (#143, #163):** миграция обновляет конфиг колонки `actions` в `ms3_grid_fields` — добавлены действия `duplicate` и `publish` (логика уже была в Vue-компоненте)
- **AuthUI.initTabSupport() сбрасывала все вкладки Bootstrap на странице (#180):** fallback без Bootstrap JS ограничен областью блока логина/регистрации (`_findAuthTabScope`), сброс `.nav-link` и `.tab-pane` только внутри своей группы (`closest('.nav')`, `:scope > .tab-pane`), защита от повторной инициализации

---

### 🚀 Версия 1.7.0-beta1

**Тип релиза:** MINOR (beta) — inline-edit, форматированные плейсхолдеры, миграция галереи на Vue, баг-фиксы

---

#### ♻️ Рефакторинг

**Удаление неиспользуемой настройки ms3_category_grid_fields (#145, #146):**
- Удалена логика `ms3_category_grid_fields`, `grid_fields`, `option_fields`, `product_fields` из контроллера `category/update`
- Удалён неиспользуемый процессор `Processors\Category\GetList`
- Из `Processors\Product\GetList` убрана мёртвая ветка с опциями (процессор вызывается только с `combo: true`)

**Упрощение Product\GetList — удаление non-combo логики (#151, #152):**
- Удалена non-combo ветка из `prepareArray()` (actions, preview_url, cls, category_name, округление price/weight)
- Удалены non-combo ветки из `prepareQueryBeforeCount()` (full select, parent-фильтрация, nested products, join msCategoryMember)
- Добавлена валидация `combo: true` в `initialize()`

**Миграция галереи товара с ExtJS на Vue (#112, #150):**
- ExtJS прослойка (gallery.panel, toolbar, view, window, ext.ddview) заменена чистыми Vue-компонентами
- Новая архитектура: `ProductGallery` (оркестратор) → `ProductGalleryGrid` (vuedraggable + ContextMenu + поиск + пагинация) + `ProductGalleryToolbar` (выбор source + bulk actions) + `ProductGalleryEditDialog`
- `useGalleryApi` composable — все API-вызовы к Gallery процессорам через коннектор
- Новый процессор `Product\UpdateSource` — смена media source без сохранения всей формы
- `GalleryUploader` (Uppy) встроен в `product-tabs` бандл, отдельный entry point `gallery-uploader` удалён
- Удалено 6 ExtJS-файлов (~900 строк), добавлено 5 Vue-компонентов + composable (~1300 строк)

#### ✨ Добавлено

**Inline-редактирование в таблице товаров категории (#116, #134):**
- Двойной клик по ячейке → редактирование прямо в таблице (text, number, boolean/checkbox)
- Настройка редактируемых полей и типа редактора в «Утилиты → Поля таблицы» для грида «Товары категории»
- Бэкенд: whitelist полей в `ProductDataService`, валидация, проверка прав, корректные HTTP-коды ошибок
- Поле `published` обновляет ресурс msProduct с вызовом событий `OnDocPublished`/`OnDocUnPublished`

**Форматированные плейсхолдеры валюты и веса (#144, #147):**
- Системные настройки (`ms3_currency_symbol`, `ms3_currency_position`, новая `ms3_weight_unit`) — единый источник истины для отображения валюты и единиц веса
- Новые `*_formatted` плейсхолдеры во всех сниппетах и чанках: `price_formatted`, `cost_formatted`, `old_price_formatted`, `old_cost_formatted`, `weight_formatted`, `discount_price_formatted`, `discount_cost_formatted`
- `Format::weightWithUnit()`, `Format::getWeightUnit()` — форматирование веса с единицей измерения
- Лексиконы `ms3_frontend_currency` и `ms3_frontend_weight_unit` сохранены, но core-чанки их больше не используют

**Поддержка кастомных полей в валидации заказа (#135):**
- Кастомные поля из правил валидации доставки (например, чекбокс `agreement` с правилом `required|accepted`) сохраняются в `draft->properties['_validated']` между `order/add` и `order/submit`
- При создании заказа кастомные поля передаются в события `msOnBeforeCreateOrder` / `msOnCreateOrder` через параметр `customFields`, после чего очищаются
- Чекбоксы на фронтенде отправляют состояние `input.checked` (`'1'`/`'0'`) вместо статического атрибута `value`

#### 🐛 Исправлено

- **Не удалялись опции товара в админке (#148, #149):** форма отправляла `options-color[]`, но `substr($key, 8)` давал ключ `color[]` вместо `color` — опция не матчилась и не удалялась. Парсинг вынесен в `Utils::extractOptionKey()` с `rtrim('[]')`. Также исправлена обработка пустого массива опций в `OptionSyncService`
- **Визуальный редактор (TinyMCE) не работал в категории товаров (#156):** `item_j.value = "<p></p>"` выполнялся безусловно, перезатирая контент при редактировании. Блок `modx-resource-content` был исключён из формы, `loadRichTextEditor()` закомментирован — всё восстановлено
- **Удаление кастомных опций категорий при сохранении товара (#153, #158):** `saveOptions(null, true)` при автосборе только JSON-полей вызывал `removeUnusedOptions` и удалял кастомные опции категорий (proizvoditel, brend и т.п.) — при `options=null` теперь принудительно `removeOther=false`
- **Пропажа пункта «создать Документ» в контекстном меню категории (#153, #158):** Form Customization не всегда выставлял CSS-класс `pnew_modDocument` на узле дерева — добавлен fallback на общий класс `pnew` для ядровых типов ресурсов. Убран дебаг `console.log`
- **Кнопки «Дублировать» и «Удалить» не отображались у товаров и категорий (#128, #133):** MODX JS использует строгое сравнение `=== 1` для permission-флагов, а `json_encode(true)` даёт `true` вместо `1` — добавлен `(int)` cast для `canDuplicate`, `canDelete`, `canCreateRoot` в контроллерах update
- **`empty('0')` в OrderFieldManager::add() (#135):** значение `'0'` больше не считается пустым — фикс PHP-ловушки `empty('0') === true`
- **Return type `OrderAddressManager::saveToCustomerAddresses()` (#135):** метод возвращал `bool`, но был объявлен как `?msCustomerAddress` — исправлен на `bool`
- **`save_address` при снятии чекбокса (#135):** раньше снятие чекбокса игнорировалось (значение `'0'` не заходило в обработчик), теперь корректно удаляет ключ из properties
- **Табличный префикс в relation-полях grid config (#136):** при указании прямого имени таблицы (например `ms3_orders`) в конфигурации связанного поля не добавлялся префикс MODX — агрегатные значения (COUNT, SUM и др.) возвращали нули
- **Сортировка и серверная пагинация в таблице клиентов (#137):** клик по заголовку сортируемой колонки менял иконку, но данные не сортировались — `getIterator()` получал параметры сортировки/пагинации как `$cacheFlag` вместо xPDO-запроса
- **Статус заказа не отображался в истории заказов клиента (#139):** ad-hoc поля из LEFT JOIN (`status_name`, `status_color`) не гидрировались в xPDO-объекты (`hydrate_adhoc_fields` выключен по умолчанию) — заменено на предзагрузку статусов в map. Та же проблема исправлена для данных товаров в деталях заказа (`pagetitle`, `article`, `old_price`). Добавлен `#` к CSS-цветам в чанках
- **Некорректные URL в ЛК заказов клиента (#141):** кнопка «Сбросить» фильтра и ссылки пагинации вели на корень сайта (`/?`) из-за `<base href>` в шаблоне MODX — URL формируются server-side через `makeUrl()` + `http_build_query()`, пагинация сохраняет фильтр по статусу. Добавлены недостающие SVG-иконки в спрайт (`icon-arrow-left`, `icon-truck`, `icon-credit-card`, `icon-message`), исправлен `fill` → `stroke` для Feather-иконок в деталях заказа
- **Некорректные URL и хардкод английских сообщений в ЛК адресов (#142):** кнопки «Добавить адрес», «Редактировать», «Отмена» вели на корень сайта — URL формируются server-side через `makeUrl()` + `http_build_query()`. API-контроллер `CustomerAddressController` содержал хардкод английских строк вместо лексиконов — все сообщения заменены на `$this->modx->lexicon()`, добавлено 7 ключей в ru/en лексиконы
- **Отсутствие ключа `desc` в свойствах сниппетов и источников при сборке пакета (#127):** добавлена инициализация `desc` пустой строкой если ключ отсутствует в определении свойства — устраняет warning/ошибку в `resolver_04_sources` и `resolver_08_snippet_properties`

#### ⚠️ Breaking changes

- **`cost_formatted` включает символ валюты (#147):** `cost_formatted` в списке заказов ЛК теперь включает символ валюты — кастомные чанки, добавляющие валюту вручную, получат двойной символ

---

### 🚀 Версия 1.6.0-beta1

**Тип релиза:** MINOR (beta) — httpOnly cookie auth, отмена заказов, community PRs

---

#### ♻️ Рефакторинг

**Интеграция standalone ЛК-модулей в архитектуру ms3 (#126):**
- Три standalone модуля (`order-cancel.js`, `customer-addresses.js`, `auth-forms.js`) заменены на UI-классы в единой архитектуре (API → UI → hooks → message)
- Новый `AuthUI` — класс для форм авторизации/регистрации с хуками `beforeLogin`/`afterLogin`, `beforeRegister`/`afterRegister`
- `CustomerUI` расширен: отмена заказов, управление адресами (set default, delete) с хуками
- `CustomerAPI` расширен: +4 метода (`login`, `register`, `setDefaultAddress`, `cancelOrder`)
- 6 новых селекторов в `Selectors.js` для ЛК-компонентов

**Promise-based confirm dialog (#126):**
- Новый модуль `confirm.js` — Bootstrap Modal с fallback на native `confirm()`
- i18n кнопок по атрибуту `<html lang>` (ru/en), переопределение через `ms3Lexicon` или параметры
- Декларативная привязка через `data-ms3-confirm` атрибут на любом элементе
- Заменяет все `confirm()` в ЛК: отмена заказа, удаление адреса, выход

**UUID в URL заказов (#126):**
- Ссылки на детали заказа используют `uuid` вместо integer `id` — безопаснее, не раскрывает количество заказов
- Валидация формата UUID из `$_GET` перед запросом к БД

#### ✨ Добавлено

**Отмена заказа покупателем (#119, Issue #117):**
- API endpoint `POST /api/v1/customer/orders/{id}/cancel` с авторизацией
- Кнопка «Отменить заказ» в списке заказов и на странице деталей
- Настройка `ms3_customer_cancel_allowed_statuses` — разрешённые статусы для отмены (по умолчанию: новый, оплаченный)

**Запоминание активной вкладки товара (#120, Issue #111):**
- При переключении вкладки ключ сохраняется в `localStorage`, при перезагрузке восстанавливается
- Настройка `ms3_product_remember_tabs` (по умолчанию включена)

#### 🐛 Исправлено

- **httpOnly cookie token architecture (#124):** единый httpOnly cookie `ms3_token` вместо 4 несинхронизированных хранилищ (localStorage, `$_SESSION`, `ms3_customer_tokens`, `msCustomer.token`). Middleware injection (`$_COOKIE` → `$_REQUEST`) для обратной совместимости. Корзина сохраняется при логине/регистрации.
- Корректное отображение кнопки «Сохранить» для товаров и категорий в MODX 3.2 (#118) — явное вычисление `canSave`/`locked` с учётом `save_document`, компонентных permissions и `checkPolicy('save')`
- Формат `data` в политиках доступа для совместимости с апгрейдом MODX (#107, Issue #100) — устранено двойное JSON-кодирование при сборке пакета

#### ⚠️ Breaking changes

- **Register.php response format (#124):** поле `token` изменено с объекта `{token, expires_at}` на строку. `expires_at` вынесен на верхний уровень ответа. Кастомные темы, обращающиеся к `result.object.token.token`, потребуют обновления.

---

## Февраль 2026

### 🚀 Версия 1.5.0-beta1

**Тип релиза:** MINOR (beta) — селекторы, обработка ошибок, community PRs

---

#### ✨ Добавлено

**Централизация селекторов (Issue #18):**
- Новый модуль `Selectors.js` с дефолтными селекторами для всех UI-компонентов
- Переопределение через `ms3Config.selectors` — частичное слияние с дефолтами
- UI-классы (CartUI, OrderUI, QuantityUI, CustomerUI, ProductCardUI) используют селекторы из конфига
- Миграция для добавления `Selectors.js` в `ms3_frontend_assets`

**Обработка отсутствия сервиса ms3 (Issue #68):**
- `ServiceCheckMiddleware` — проверка `has('ms3')` на уровне роутера для всех `/api/v1` маршрутов
- Проверка `has('ms3')` в точках входа (api.php, connector.php), плагине и всех сниппетах
- 503 + лог вместо необработанного Exception при отсутствии сервиса

**data-* атрибуты как основные селекторы (Issue #17):**
- `data-ms3-form`, `data-ms3-qty`, `data-ms3-cart-options`, `data-ms3-product-card` и др.
- CSS-классы сохранены как fallback для обратной совместимости

**Пагинация и количество строк в гриде заказов (Issue #78):**
- Выбор количества строк на странице
- Кнопки перехода в начало/конец списка
- Хеш-параметры в чанках

**Устойчивость к обрыву MySQL-соединения при установке:**
- Reconnect-функция в `resolver_02_migrations.php` — проверка и восстановление соединения
- Покрывает gap после скачивания пакетов (resolver_01, до 180s) и после выполнения Phinx-миграций
- Защищает от `MySQL server has gone away` на хостингах с коротким `wait_timeout`

**Прочее:**
- Событие `ms3:cart:updated` при успешном оформлении заказа (#96)
- Параметр `formatPrices` в сниппете `msOrderTotal`
- Сортировка в таблице списка заказов (Vue)

#### 🐛 Исправлено

- Исправлены неточности в лексиконах (Issue #21)
- Удалён `action` из конфигурации меню miniShop3 (#94)
- Очистка EAV-опций из формы товара
- Пустой список заказов из-за лишнего `GROUP BY`
- Идемпотентность seed-миграций грид-конфигурации
- `Response::error` теперь включает корректный HTTP-код (`code`) в JSON-ответ
- `CartController::change()`/`remove()` — исправлен тип возвращаемого значения (array вместо Response)
- Корректные дефолтные ID статусов заказов с fallback для нулевых значений
- `getIterator` для msProduct/msCategory — добавлен `class_key` в критерии

#### 🔧 Изменено

- Удалены избыточные проверки прав в `initialize()` процессоров (#95)
- `CustomerAddressController::getAuthorizedCustomer()` упрощён (middleware гарантирует сервис)
- Убран fallback-регистрация сервиса в `api.php` — при сбое bootstrap возвращается 503

---

### [2026-02-07] 🚀 Версия 1.4.0-beta1

**Тип релиза:** MINOR (beta) — Notification Center, улучшения msOrder

---

#### ✨ Добавлено

**Notification Center — Email уведомления:**
- Email-уведомления при смене статуса заказа
- Поддержка получателей: `manager` и `customer`
- Настраиваемые шаблоны через чанки (поддержка `@FILE` синтаксиса)
- Миграция для seed-конфигурации уведомлений
- Лексиконы для email-сообщений (ru/en)

**Notification Center — Telegram уведомления:**
- Telegram-уведомления для менеджеров при смене статуса заказа
- Системные настройки: `ms3_telegram_bot_token`, `ms3_telegram_manager_chat_id`
- Локализованные сообщения с информацией о заказе
- Поддержка кастомных шаблонов через чанки

**Сниппет msOrder — параметр `customerFields`:**
- Новый параметр для маппинга полей msCustomer на поля заказа
- Формат: JSON `{"order_field": "customer_field"}`
- Автозаполнение данных авторизованного клиента

**Сниппет msOrder — выбор источника данных:**
- При `ms3_customer_sync_enabled = true`: данные берутся из modUserProfile
- При `ms3_customer_sync_enabled = false`: данные берутся из msCustomer
- Исключает конфликт между двумя источниками

**События для интеграции внешних пакетов:**
- `msOnProductsLoad` — bulk-загрузка данных для списка товаров
- `msOnProductPrepare` — обогащение данных отдельного товара
- Параметр `usePackages` для активации (ms3Variants, msBrands и др.)

**Страница "Помощь и поддержка":**
- Переведена на Vue 3 + PrimeVue для унификации UI
- Карточки ресурсов с hover-эффектами
- Быстрые ссылки на разделы админки

**Конвертация ресурса в товар:**
- При смене `class_key` обычного ресурса на `msProduct`:
  - Автоматически создаётся запись `msProductData`
  - Товар скрывается из дерева согласно настройке `ms3_product_show_in_tree_default`
- Логика в сервисе `ProductService::handleConversion()`

#### 🔧 Изменено

**Централизация сервисов:**
- Все 41 сервис MiniShop3 теперь в ServiceRegistry
- bootstrap.php содержит только основной `ms3` сервис
- Добавлен `ms3_filter_config` в ServiceRegistry

#### 🐛 Исправлено

**Сниппет msOrder:**
- Удалён мёртвый код валидации POST (валидация в OrderSubmitHandler)
- Удалены неиспользуемые CSS-классы ошибок из чанка `ms3_order.tpl`

**Доступ к `ms3.config` в Vue компонентах:**
- Исправлен доступ к глобальной переменной `ms3`
- Добавлена утилита `getMs3Config()` в `utils/modx.js`

**Синхронизация XML схемы и PHP моделей:**
- Поле `stock` в модели `msProductData`
- Поле `default_value` в модели `msExtraField`
- Модель `msGridField` в XML схему

**Стили вкладки Options:**
- Исправлены стили компонента ProductOptions.vue
- Удалён deprecated CSS код

#### 🗑️ Удалено

**Очистка неиспользуемых файлов:**
- `misc/plupload/` — библиотека Plupload
- `model-fields/model-fields.wrapper.js`
- `utilities/gallery/panel.js`
- `product.grid.js` — грид товаров полностью на Vue
- `help.css` — стили теперь в Vue компоненте
- Лексиконы Plupload
- Неиспользуемые процессоры уведомлений

---

## Январь 2026

### Дополнения к 1.4.0-beta1 (разработка январь 2026)

**Эти изменения вошли в релиз 1.4.0-beta1 (07.02.2026)**

---

#### ✨ Добавлено

**Админка — Редактирование товара:**
- Вложенные вкладки товара: верхний уровень (ExtJS) — Document, Товар, Page Settings, Access Permissions; вложенный уровень (Vue) — Properties, Gallery, Categories, Links, Options
- Plugin Registry API для сторонних компонентов: `window.MS3ProductTabsRegistry.register({ key, title, type, xtype/component })`
- Новый компонент ProductTabs.vue с PrimeVue TabView

**Кастомные поля:**
- Новый тип поля «Выпадающий список» (xtype: `ms3-combo-select`) для Extra Fields
- UI для настройки опций в утилитах «Свои поля» (формат: `value==label`)
- Рендеринг PrimeVue Dropdown в форме редактирования товара

**Фронтенд:**
- Динамическое обновление виджета msOrderTotal при изменении корзины
- Новый чанк `tpl.msOrderTotal` с дефолтной разметкой
- CSS стили для виджета `.ms3-order-total`

**Сниппет msProducts:**
- Поддержка msCategoryMember для вывода товаров из дополнительных категорий
- Собственная обработка parents (включения и исключения) как воркараунд для pdoTools
- Оптимизированный поиск дочерних категорий (только msCategory, не все ресурсы)
- **События для интеграции внешних пакетов:** `msOnProductsLoad` и `msOnProductPrepare`
- **Параметр `usePackages`** — активация загрузки данных из внешних пакетов (ms3Variants, msBrands и др.)
- Bulk-загрузка дополнительных данных одним запросом (избежание проблемы N+1)

**Админка — Настройки:**
- Подсказки к полям названия способов доставки и оплаты (можно указать ключ лексикона)

**Установка:**
- VueTools добавлен в зависимости — устанавливается автоматически при отсутствии

#### 🐛 Исправлено

**Авторизация:**
- Унифицирована авторизация в CustomerAddressController — теперь поддерживает и API-токен, и session customer_id

**Миграции:**
- Добавлена миграция для колонки `select_options` в таблице `ms3_extra_fields`

---

### [2026-01-24] 🚀 Версия 1.2.3-beta1

**Тип релиза:** PATCH (beta) — улучшения и исправления

---

#### ✨ Добавлено

**Админка — Vue Manager:**
- Локализация PrimeVue через `@vuetools/usePrimeVueLocale` во всех Vue-приложениях (заказы, товары, клиенты, доставки, оплаты, производители, связи, статусы, уведомления, категории товаров, галерея, импорт, поля и т.д.)
- `usePrimeVueLocale` добавлен в vuetoolsComposables в vite.config.js
- Унифицирован импорт темы Aura: `@primeuix/themes/aura` во всех entry points
- Добавлен `cultureKey` в мок MODx в api-test.html
- Зависимость темы: `@primevue/themes` заменена на `@primeuix/themes` (PrimeVue 4.x)

#### 🐛 Исправлено

**ESLint:**
- Удалён неиспользуемый параметр `mutations` в MutationObserver (entry points)
- Удалён неиспользуемый параметр `prefixToIgnore` в vite.config.js (postcss-prefix-selector)
- Добавлены глобальные переменные MODX (`Ext`, `MODx`, `ms3`) в eslint.config.js
- Откачено правило `no-unused-vars` с `argsIgnorePattern`/`varsIgnorePattern`/`caughtErrorsIgnorePattern` — исправления через удаление неиспользуемого кода и optional catch binding

**OrderView.vue:**
- Согласована сигнатура `onOptionTypeChange(row)` и вызов в шаблоне (удалён лишний аргумент `index`)

#### 📁 Изменённые файлы

```
vueManager/src/entries/api-test.js
vueManager/src/entries/category-products.js
vueManager/src/entries/customers.js
vueManager/src/entries/deliveries.js
vueManager/src/entries/extra-fields.js
vueManager/src/entries/fields-management.js
vueManager/src/entries/grid-fields-config.js
vueManager/src/entries/import.js
vueManager/src/entries/links.js
vueManager/src/entries/model-fields.js
vueManager/src/entries/notifications.js
vueManager/src/entries/order.js
vueManager/src/entries/orders.js
vueManager/src/entries/payments.js
vueManager/src/entries/product-tabs.js
vueManager/src/entries/statuses.js
vueManager/src/entries/utilities-gallery.js
vueManager/src/entries/vendors.js
vueManager/vite.config.js
vueManager/api-test.html
vueManager/package.json
vueManager/eslint.config.js
vueManager/src/components/OrderView.vue
```


## Архив

Подробная история изменений по месяцам:

- [Январь 2026](changelogs/2026-01.md)
- [Декабрь 2025](changelogs/2025-12.md)
- [Ноябрь 2025](changelogs/2025-11.md)
- [Октябрь 2025](changelogs/2025-10.md)
- [Архив (2024 и ранее)](changelogs/archive.md)

---

**Полная история:** См. [архивные файлы](changelogs/) для детальной информации по предыдущим месяцам.
