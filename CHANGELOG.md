# CHANGELOG - История изменений MiniShop3

Этот файл содержит хронологическую историю всех значительных изменений в проекте.

> **История релизов (автообновляется):** [RELEASES.md](RELEASES.md) — генерируется из conventional commits при каждом релизе через semantic-release.

## Навигация

- **Текущий месяц:** [Февраль 2026](#февраль-2026) (ниже)
- **Предыдущий месяц:** [Январь 2026](#январь-2026) (ниже)
- **Архив по месяцам:**
  - [Декабрь 2025](changelogs/2025-12.md)
  - [Ноябрь 2025](changelogs/2025-11.md)
  - [Октябрь 2025](changelogs/2025-10.md)
  - [Архив (2024 и ранее)](changelogs/archive.md)

---

## Февраль 2026

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

### Не выпущено Февраль 2026

**Тип релиза:** MINOR (beta) — новые возможности и исправления

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
