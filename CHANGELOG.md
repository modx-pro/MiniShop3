# CHANGELOG - История изменений MiniShop3

Этот файл содержит хронологическую историю всех значительных изменений в проекте.

## Навигация

- **Текущий месяц:** [Май 2026](#май-2026) (ниже)
- **Предыдущий месяц:** [Апрель 2026](#апрель-2026) (ниже)
- **Ещё раньше:** [Март 2026](#март-2026), [Февраль 2026](#февраль-2026), [Январь 2026](#январь-2026) (ниже)
- **Архив по месяцам:**
  - [Декабрь 2025](changelogs/2025-12.md)
  - [Ноябрь 2025](changelogs/2025-11.md)
  - [Октябрь 2025](changelogs/2025-10.md)
  - [Архив (2024 и ранее)](changelogs/archive.md)

---

## Май 2026

### Разработка

#### 🐛 Исправлено

**События с мутацией данных через `returnedValues` (#219):**
- `msOnBeforeSendNotification` теперь применяет `returnedValues['recipient']` и `returnedValues['channels']`, поэтому плагины могут изменить получателя и каналы перед отправкой уведомления.
- `msOnBeforeImport` и `msOnImportRow` применяют `returnedValues` к параметрам импорта и данным строки (`data`, `tvData`, `optionData`, `gallery`).
- `msOnProductsLoad` и `msOnProductPrepare` применяют `returnedValues['rows']` / `returnedValues['row']`, чтобы bulk-обогащение списка товаров и подготовка отдельной строки доходили до рендера.

**Сниппет `msCart` — не глушить вывод при `msorder` по умолчанию (#249):**
- При `?msorder=...` сниппет `msCart` больше не возвращает пустую строку по умолчанию — корзина загружается и отдаёт чанк / `return=data` (в т.ч. мини-корзина в общем layout продолжает работать на странице «спасибо»).
- Новый параметр `hideOnThanks` (`combo-boolean`): установите `1` / `true`, чтобы вернуть прежнее поведение «не выводить корзину на странице «спасибо»» для конкретного вызова (например, второй блок корзины на странице с номером заказа в URL).
- **Миграция:** если на странице с `?msorder=...` был второй блок `msCart`, который раньше пропадал сам собой, и это было нужно, добавьте к этому вызову параметр `hideOnThanks=1` или скройте блок в шаблоне.

**Опции товара — удаление не применялось после сохранения (дополнение к #199 / #202):**
- В `Processors\Product\Update` после вызова родительского `afterSave()` свойство процессора `options` в MODX 3 часто пустое, из‑за чего не выполнялся `ProductDataService::saveOptions(..., removeOther: true)` и строки в `ms3_product_options` не синхронизировались с формой. Массив из полей `options-*` сохраняется в `beforeSet` в `$ms3ProductFormOptions` и передаётся в сервис после сохранения ресурса.

**Категория → опции — PHP Warning `Undefined array key "id"` (PHP 8+) (#238):**
- У `msCategoryOption` составной первичный ключ; в выборке списка нет столбца `id`. `CategoryOptionsController::formatRow()` больше не обращается к несуществующему ключу: если `id` нет в строке PDO, для поля ответа `id` используется `option_id` (в рамках одной категории уникально; Vue-грид и так использует `data-key="option_id"`).

**Товар — сохранение без вкладки «Категории» обнуляло дополнительные категории (#238):**
- `ProductDataService::saveCategories()` при отсутствии ключа `categories` в `_fields` (POST не содержит поля, пока дерево вкладки не отрисовалось) больше не трактует это как пустой список и не вызывает `removeCollection`. Поведение согласовано с `saveLinks()`: явная передача `categories` по-прежнему синхронизирует `msCategoryMember` (в том числе пустой массив после открытия вкладки).
- **⚠️ Изменение контракта:** интеграции, сознательно полагавшиеся на «нет `categories` в POST → очистить связи», должны теперь передавать пустой массив явно.

**Web API покупателя — отсутствующие маршруты CustomerAPI (#241):**
- Добавлен `POST /api/v1/customer/add` для быстрого обновления полей профиля (`first_name`, `last_name`, `email`, `phone`) через существующий `CustomerAPI.add()` и `CustomerUI.handleAdd()`.
- Добавлен `POST /api/v1/customer/changeAddress` как совместимый endpoint для выбора сохранённого адреса в черновике заказа по `address_hash`.
- Для быстрого обновления профиля добавлена whitelist-валидация и отдельное сообщение lexicon на `ru/en`; проверка уникальности email и сброс `email_verified_at` вынесены в общие методы контроллера профиля.

#### ✨ Добавлено

**Отрицательная доп. стоимость доставки и оплаты (#211):**
- Поле `price` у способов доставки и оплаты теперь поддерживает отрицательные фиксированные значения и проценты (`-100%`…`100%`).
- Сохранение настроек доставки и оплаты использует общий нормализатор доп. стоимости, чтобы одинаково обрабатывать `-10`, `-10%`, запятые и лишние символы.
- Подсказки Vue-админки уточняют, что доп. стоимость может быть отрицательной и процентной.

**Редактор правил доставки — поля из расширенной конфигурации (#215):**
- `ValidationRulesEditor` подгружает поля `msOrder` и `msOrderAddress` из API «поля модели» и активные поля Object Extension для этих классов.
- В списке выбора поля для `validation_rules` доставки доступны кастомные колонки и подписи из админки; служебные поля (id, стоимости, delivery_id и т.д.) отфильтрованы.

---

## Апрель 2026

### [2026-04-27] 🚀 Версия 1.10.1-beta1

**Тип релиза:** PATCH (beta) — точечные исправления и восстановление контракта событий

---

#### 🐛 Исправлено

**Manager API — события жизненного цикла позиции заказа (#208, closes #207):**
- Vue-админка дёргает `msOnBefore/Create/Update/Remove OrderProduct` через `Utils::invokeEvent` при добавлении/изменении/удалении позиций заказа — раньше события были зарегистрированы в `events.php`, но `OrdersController` их не вызывал, и сторонние подписчики (ms3PromoCode и т.п.) не срабатывали.
- Before-hooks могут заблокировать операцию через `Response::error(400)`. After-hooks логируются на WARN-уровне с маркером `(persistence already done)` — ошибка плагина после `save()`/`remove()` не возвращается клиенту как 4xx, потому что persistence уже произошёл.

**Опции товара — переключение групп больше не сбрасывает значения (#230):**
- `ProductOptionsTab.vue` рендерит все группы сразу с `v-show` вместо `v-if`-style монтирования только активной — локальные `value = ref(...)` и hidden inputs сохраняются между переключениями вкладок.
- Раньше значения, введённые в неактивной группе, терялись и не доезжали на бэкенд (MODX/ExtJS `BasicForm.getValues()` собирает только инпуты, физически присутствующие в DOM на момент сабмита).

**Опции товара — висячие `modcategory_id` после удаления категории (#228):**
- Плагин `MiniShop3` подписан на `OnCategoryRemove` — обнуляет `msOption.modcategory_id` для всех опций, ссылавшихся на удалённую `modCategory`. Зеркалит штатный паттерн MODX, где `modCategory::remove()` сбрасывает `category` у `modChunk`/`modPlugin`/`modSnippet`/`modTemplate`/`modTemplateVar`.
- Без фикса в карточке товара мог появиться второй таб «Без группы» с разными мёртвыми `modcategory_id`.

**OrderFinalizeService вызывал несуществующие события (#217):**
- `msOnBeforeFinalizeOrder` / `msOnFinalizeOrder` нигде не были зарегистрированы как `modEvent`, подписать плагин через UI было нельзя. Заменены на `msOnBeforeMgrCreateOrder` / `msOnMgrCreateOrder` — уже существовали в билдере, но никем не вызывались.

**Импорт товаров — неправильный ключ system setting для дефолтного шаблона (#210):**
- `Processors\Utilities\Import\Fields::getTvFields()` использовал несуществующий ключ `ms3_product_default_template`. Корректный ключ — `ms3_template_product_default` (используется в `controllers/product/create.class.php`). Из-за опечатки на экране импорта блок «TV-поля» перечислял все TV вместо только привязанных к шаблону-дефолту.

**Cart API — отсутствующий лексикон (#223, closes #222):**
- Добавлен ключ `ms3_cart_get_success` в `lexicon/{ru,en}/cart.inc.php` — ключ использовался в `Cart::get()`, но без перевода MODX писал в лог `Language string not found`.

**ServiceRegistry — лишний debug-шум в логах (#225, closes #224):**
- На штатной установке без кастомизации сервисов `loadMainConfig()` / `loadAddonConfigs()` писали DEBUG про отсутствие дефолтных override-путей. Теперь логирование срабатывает только если оператор явно задал `ms3_services_config` / `ms3_services_addons_dir` через system settings, а файла/папки по этому пути нет.

**Подтверждение email на витрине — ссылка из письма и повторная отправка в ЛК (#226):**
- Ссылка по умолчанию ведёт на Web API `api.php?route=…/email/verify&token=…&html=1` (раньше — несуществующий `verify-email` на `site_url`); кастомный URL — системная настройка `ms3_email_verification_url`. После клика в письме: редирект на сайт с `ms3_email_verified=1|0` (для success — опционально `ms3_email_verification_success_url`); `format=json` по-прежнему отдаёт JSON.
- `Response` и `api.php` поддерживают HTTP-редирект вместо JSON для таких маршрутов.
- Подключены `CustomerAPI::resendVerificationEmail`, `CustomerUI`, селекторы и `ms3_customer_profile.tpl` — кнопка resend инициирует `POST /api/v1/customer/email/resend-verification`. Добавлены README, лексиконы, smoke-тест `core/components/minishop3/tests/EmailVerificationUrlTest.php`.

#### 📁 Изменённые файлы

```
_build/elements/plugins.php
core/components/minishop3/elements/plugins/minishop3.php
core/components/minishop3/lexicon/en/cart.inc.php
core/components/minishop3/lexicon/ru/cart.inc.php
core/components/minishop3/src/Controllers/Api/Manager/OrdersController.php
core/components/minishop3/src/Processors/Utilities/Import/Fields.php
core/components/minishop3/src/ServiceRegistry.php
core/components/minishop3/src/Services/Order/OrderFinalizeService.php
vueManager/src/components/product/ProductOptionsTab.vue
```

---

### 🚀 Версия 1.10.0-beta1

**Тип релиза:** MINOR (beta) — полный перевод управления опциями с ExtJS на Vue, рефакторинг карточки заказа, self-heal миграция

---

#### ✨ Добавлено

**Управление опциями товара полностью на Vue (#205, в т.ч. #200, #203):**
- `Настройки → Опции` — грид (DataTable), дерево категорий MODX (`PrimeVue Tree`) с независимыми чекбоксами и контекстным меню (обновить / развернуть / свернуть / выделить все вложенные / снять все), диалог создания-редактирования с формой и деревом категорий для привязки, редактор значений для `combobox` / `comboMultiple` / `comboColors` (drag-drop сортировка через `vuedraggable`, `PrimeVue ColorPicker` для цветов)
- `Категория товара → вкладка Опции` — Vue-грид привязок: drag-drop сортировка (`rowReorder`), inline-редактор поля «Значение по умолчанию», массовые действия (активировать / деактивировать / обязательная / необязательная / удалить), диалоги «Добавить опцию» и «Копировать опции из другой категории»
- `Карточка товара → вкладка Опции товара` — универсальный рендер всех 10 типов (textfield, numberfield, textarea, checkbox, comboBoolean, combobox, comboMultiple, comboColors, comboOptions, datefield); `comboOptions` работает как чипы (PrimeVue `InputChips`) с автодобавлением по Enter / запятой / blur + клик-подсказки под полем из `/api/mgr/options/suggestions`
- Per-category caption/description override в `ms3_category_options` (#200): при непустом значении показывается вместо глобального `msOption.caption/description` и на витрине, и в админке; inline-редактор в гриде категории + поля в диалоге «Добавить опцию»; пакетная подгрузка без N+1 для `loadForProducts`
- REST API `/api/mgr/options/*` и `/api/mgr/categories/{id}/options/*` заменил legacy `Processors/Settings/Option/*` и `Processors/Category/Option/*`
- Декларативный schema API в `msOptionType::getSchema()` — backend отдаёт декларативное описание типа вместо ExtJS JS-строк

**Self-heal миграция `msOrder` / `msOrderAddress` (#201):** если seed `ms3_model_fields` / `ms3_model_field_sections` частично не применился (админка показывала `ms3_model_fields_empty`), миграция `20260420120000_repair_order_model_fields_if_missing` добавляет только отсутствующие записи — пользовательские настройки секций/ширин не затирает (update ограничен `WHERE section_id IS NULL`).

#### ♻️ Рефакторинг

- **Опции:** удалены 7 ExtJS-файлов (`settings/option/*`, `category/option.*`) и 22 PHP-процессора (~2600 строк устаревшего кода)
- **Карточка заказа — provide/inject вместо props-цепочки (#196, #204):** `provide(ORDER_CONTEXT_KEY)` в `OrderView`, composables `useOrderFormatters`, `useOrderFieldHelpers`, `useOrderLogFormatters`; вкладки получают только специфичные данные через props; безопасный `inject` до деструктуризации
- `OptionLoaderService::convertPreloadedValue` — case-insensitive матч типов; multi-значения теперь корректно восстанавливаются при reload для `comboMultiple`, `comboColors`, `comboOptions` (раньше возвращалось только первое)
- Multi-value опции товара отправляются одним hidden input с JSON-массивом + декодирование на сервере через `Utils::decodeOptionValue()` — обход ограничения `ExtJS BasicForm.getValues()`, который читал только последний input при нескольких hidden с одинаковым name
- `ProductTabs.vue` — вкладка опций больше не монтирует `Ext.create('modx-vtabs')` + `ms3.utils.getExtField()`, всё рендерится Vue-компонентом `ProductOptionsTab` с вертикальными группами по `modcategory_id`

#### 🐛 Исправлено

- **Удалённая опция товара снова появлялась после сохранения (#199, #202):** при сохранении из полей `options-*` теперь используется `removeOther=true` — ключи, которых нет в POST (после ручного удаления или копирования), удаляются из `msProductOption`. Автосинхронизация только JSON-полей по-прежнему через `saveOptions(null)` с принудительным `removeOther=false`, чтобы не повторить регрессию #153/#158.
- Checkbox-опции товара `boolean`/`modx-combo-boolean`/`combo-boolean` отображались узкой полоской («V\|») при `labelAlign: 'top'` — регрессия `anchor: '25%'` из `ms3.utils.js`; старый путь удалён вместе с ExtJS UI, опции теперь рендерятся через PrimeVue
- Дерево категорий в старом ExtJS-диалоге опций выводило все resource'ы и ориентировалось на `isfolder` — в Vue-дереве точный фильтр `class_key = MiniShop3\\Model\\msCategory` и leaf по `COUNT(Child.id)`
- Множественный выбор в `comboMultiple`/`comboColors` в старом коде терял все значения кроме последнего при сохранении товара
- `modcategory_id` опции можно очистить (null коэрсится в 0, поле больше не обязательно)
- DatePicker для `datefield` отправлял UTC-сдвиг (`toISOString()` давал дату на день раньше в TZ восточнее UTC) — теперь формат через `getFullYear/getMonth/getDate`

---

### 🚀 Версия 1.9.0-beta1

**Тип релиза:** MINOR (beta) — vendor extra fields, ms3_cart status sync, рефакторинг опций и заказа

---

#### ✨ Добавлено

**Сниппет `ms3_cart` — синхронизация итогов со статусом (#197):**
- После `msOnGetStatusCart` итоги в чанке/`return=data` выравниваются со всеми числовыми полями статуса (`total_cost`, `total_count`, `total_weight`, `total_discount`, `total_positions`)
- Синхронизация работает и на ранних выходах при пустой корзине
- `cost_formatted`/`weight_formatted` считаются от финального `$total` без мутации `$outputData`
- В чанк и `return=data` добавлен ключ `status` — доступ к `{$status.total_cost}` и т.д.

**Vendor extra fields — DynamicField для формы вендора (#198):**
- `VendorsGrid` заменена ручная цепочка `v-if` (3 типа) на компонент `DynamicField` (13+ типов полей, включая checkbox)
- `VendorsController`: загрузка xPDO map для extra fields, динамический `allowedFields`, `toArray()` в `formatVendor`
- `ExtraFieldsService`: создание `msProductField` только для `msProductData` (не для msVendor и других моделей)
- `DynamicField`: поддержка FileBrowser для файловых полей, `w-full` на всех инпутах, `fluid` на InputNumber, prop `idPrefix` для label/id accessibility

#### 🐛 Исправлено

- **Удалённая опция товара в админке снова появлялась после сохранения (#199):** при сохранении из полей `options-*` использовался `removeOther=false`, из‑за чего строки в `msProductOption` для ключей, которых больше нет в POST (после удаления опции в форме, в т.ч. после копирования товара), не удалялись — для явного массива опций из процессора теперь `removeOther=true`; автосинхронизация только JSON-полей по-прежнему через `saveOptions(null)` с принудительным `removeOther=false` (#153, #158)
- **Manager API затирал `msOrder.properties` данными адреса (#191):** `array_merge($order->toArray(), $address->toArray())` перезаписывал `properties` заказа значением `null` из `msOrderAddress` — выделен `mergeAddressIntoOrderData()` с исключением конфликтующих полей
- **В форме заказа manager UI показывались raw lexicon keys (#193):** `GET /api/mgr/model-fields/visible/msOrder` загружал только `minishop3:vue`, из-за чего `comboOptions` не переводили значения из `minishop3:default` и `minishop3:manager`; дополнительно исправлены order-form dropdown routes для статусов и активных доставок
- **Удаление клиента из грида не работало (#179):** DELETE-запрос выполнялся только после подтверждения в диалоге, но сетевой запрос не отправлялся
- **AuthUI.initTabSupport() сбрасывала все вкладки Bootstrap (#180):** fallback без Bootstrap JS ограничен областью блока логина/регистрации, сброс `.nav-link` и `.tab-pane` только внутри своей группы
- **Фильтр групп опций дублировал категории (#186):** dedupe список modCategory в фильтре, увеличен page size комбобокса
- **Массовое назначение опций категориям (#187):** исправлен bulk assign processor и логика select-all в дереве категорий
- **Вкладки групп опций в карточке товара (#188):** опции группируются по `modcategory_id`, нормализация id, guard для пустых `option_fields`
- **msPayment type hints в Payment Sort процессоре (#177):** заменены устаревшие type hints на msPayment

#### ♻️ Рефакторинг

- **Экран заказа — provide/inject вместо props-цепочки (#196):** `provide(ORDER_CONTEXT_KEY)` в `OrderView`, composables `useOrderFormatters`, `useOrderFieldHelpers`, `useOrderLogFormatters`; вкладки получают только данные вкладки через props; безопасный `inject` до деструктуризации
- **OrderView разбит на подкомпоненты (#176):** монолитный `OrderView.vue` разделён на `OrderInfoTab`, `OrderProductsTab`, `OrderAddressTab`, `OrderHistoryTab` + вынесен `orderFieldsLayout.css`
- **Опции товара:** Map по `modcategory_id` для вкладок, именованный page size комбобокса под `ms3.grid`, документирован GROUP BY
- **PHPStan (#174):** исправлено 109 ошибок, baseline уменьшен с 277 до 168
- **OptionsChips:** стили вынесены из scoped в non-scoped с `.vueApp` префиксом (фикс несовпадения Vite scoped хешей между чанками)
- **Prettier:** форматирование 25 Vue-компонентов

#### 📦 Зависимости

- `lodash` 4.17.23 → 4.18.1
- `vite` 6.4.1 → 6.4.2

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

- **Пустая строка в decimal/int Extra Fields ломала сохранение товара (#170):** пустое значение кастомного поля (например `wholesale_price`) вызывало MySQL ошибку `Incorrect decimal value`, `save()` возвращал `false` и категории/опции/ссылки молча не сохранялись — хардкод каста `price`/`old_price`/`weight` заменён на универсальный цикл по `_fieldMeta` для всех `float`, `integer` и `boolean` полей
- **Чекбокс «Скрыть дочерние ресурсы» не сохранялся в категориях (#161, #160):** `hide_children_in_tree` не обрабатывался в `handleCheckBoxes()` процессоров `Category/Update` и `Category/Create` — unchecked-состояние не передавалось в POST и значение сбрасывалось
- **`publish_document` передавался как bool вместо int в контроллерах (#160):** `canPublish` не приводился к `(int)` в массиве JS-конфига — MODX JS использует строгое сравнение `=== 1`, из-за чего флаг мог не срабатывать. Исправлено во всех 4 контроллерах
- **Кнопки «Дублировать» и «Опубликовать» не отображались в гриде товаров категории (#143, #163):** миграция обновляет конфиг колонки `actions` в `ms3_grid_fields` — добавлены действия `duplicate` и `publish` (логика уже была в Vue-компоненте)

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
