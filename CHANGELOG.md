# CHANGELOG - История изменений MiniShop3

Этот файл содержит хронологическую историю всех значительных изменений в проекте.

## Навигация

- **Текущий месяц:** [Январь 2026](#январь-2026) (ниже)
- **Предыдущий месяц:** [Декабрь 2025](#декабрь-2025) (ниже)
- **Архив по месяцам:**
  - [Ноябрь 2025](changelogs/2025-11.md)
  - [Октябрь 2025](changelogs/2025-10.md)
  - [Архив (2024 и ранее)](changelogs/archive.md)

---

## Январь 2026

### [2026-01-30] 🚀 Версия 1.3.0-beta1

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

**Оформление заказа:**
- Автоматический пересчёт стоимости заказа при смене способа доставки или оплаты
- При изменении корзины (добавление/удаление товаров) стоимость доставки пересчитывается автоматически
- Корректно работает порог бесплатной доставки (поле `free_delivery_amount`)

**Админка — Настройки:**
- Добавлена пагинация на вкладке «Производители» (ранее отображались только первые 20 записей)
- Добавлена пагинация на вкладке «Связи товаров»

**Админка — Категории:**
- Чекбокс «Показать вложенные» теперь активен по умолчанию

#### 🐛 Исправлено

**Расчёт стоимости заказа:**
- Итоговая сумма заказа теперь корректно включает стоимость доставки (`cost = cart_cost + delivery_cost`)
- Исправлен расчёт бесплатной доставки — теперь используется сумма корзины, переданная в метод

**Сессия и авторизация:**
- Исправлено сохранение токена сессии при авто-регистрации клиента — корзина больше не пропадает после перезагрузки страницы
- Добавлено автоматическое обновление токена при ошибке `ms3_err_token_invalid`

**Валидация заказа:**
- Валидация обязательных полей (доставка, оплата) теперь выполняется до создания customer

**Админка — UI:**
- Исправлен перевод названия способа оплаты в диалоге редактирования доставки
- Исправлены стили выпадающего списка правил валидации (z-index)
- Улучшены отступы в формах редактирования доставок и оплат

#### 📁 Изменённые файлы

```
core/components/minishop3/src/Services/Order/OrderSubmitHandler.php
core/components/minishop3/src/Services/Customer/RegisterService.php
core/components/minishop3/src/Controllers/Delivery/Delivery.php
assets/components/minishop3/js/web/ui/OrderUI.js
assets/components/minishop3/js/web/api/ApiClient.js
assets/components/minishop3/js/mgr/category/product.grid.js
vueManager/src/components/VendorsGrid.vue
vueManager/src/components/LinksGrid.vue
vueManager/src/components/DeliveriesGrid.vue
vueManager/src/components/PaymentsGrid.vue
```

---

### [2026-01-20] 🚀 Версия 1.2.2-beta1

**Тип релиза:** PATCH (beta) — исправления багов

---

#### 🐛 Исправлено

**Галерея изображений:**
- Исправлено экранирование namespace процессора в JavaScript (`MiniShop3\\Processors\\Product\\Get`)

**Чистая установка:**
- Миграции создания таблиц проверяют существование перед созданием
- Все seed-миграции используют `sort_order` (совместимость с xPDO моделями)

#### 📁 Файлы

```
assets/components/minishop3/js/mgr/product/gallery/gallery.panel.js
core/components/minishop3/migrations/20251205120000_create_model_fields_table.php
core/components/minishop3/migrations/20251205120100_seed_model_fields.php
core/components/minishop3/migrations/20251211120000_create_model_field_sections_table.php
core/components/minishop3/migrations/20251223120000_seed_vendor_model_fields.php
```

---

### [2026-01-20] 🚀 Версия 1.2.1-beta1

**Тип релиза:** PATCH (beta) — исправления багов

---

#### 🐛 Исправлено

**Совместимость с MySQL 8.0:**
- Поле `rank` переименовано в `sort_order` в таблицах `ms3_model_fields` и `ms3_model_field_sections`
- `rank` — зарезервированное слово в MySQL 8.0+ (window functions)
- Для системной таблицы `modContext` (MODX core) добавлено экранирование бэктиками

**Отправка писем подтверждения email:**
- Добавлен отсутствующий импорт `modMail`
- Исправлен формат плейсхолдеров в лексиконах (`{name}` → `[[+name]]`)

**Загрузка лексиконов:**
- Добавлена загрузка лексикона `minishop3:cart` в сниппеты

#### 🔄 Изменено

**Грид заказов в админке:**
- Статус заказа теперь использует relation-поля вместо прямого JOIN
- Новые поля: `order_status` (badge), `status_color`, `status_name` (relation)
- Поля `delivery_name`, `payment_name` переведены на тип `relation`

#### 📁 Миграции

| Миграция | Описание |
|----------|----------|
| `20260119120000` | Переименование `rank` → `sort_order` |
| `20260119220000` | Обновление полей статуса в гриде заказов |

#### 📁 Файлы

```
core/components/minishop3/src/MiniShop3.php
core/components/minishop3/schema/minishop3.mysql.schema.xml
core/components/minishop3/src/Model/msModelField.php
core/components/minishop3/src/Model/mysql/msModelField.php
core/components/minishop3/src/Controllers/Api/Manager/ModelFieldsController.php
core/components/minishop3/src/Processors/System/Element/Context/GetList.php
core/components/minishop3/lexicon/ru/vue.inc.php
core/components/minishop3/lexicon/en/vue.inc.php
vueManager/src/components/ModelFieldsGrid.vue
```

---

### [2026-01-18] 📧 Исправлена отправка писем подтверждения email

**Контекст:** Письма верификации email не отправлялись из-за отсутствующего импорта, а плейсхолдеры в письмах не заменялись на реальные значения.

---

#### 🐛 Исправлено

**EmailVerificationService.php:**
- Добавлен отсутствующий импорт `use MODX\Revolution\Mail\modMail`

**Лексиконы (ru/en customer.inc.php):**
- Формат плейсхолдеров изменён с `{name}` на `[[+name]]`
- MODX `$modx->lexicon()` требует формат `[[+name]]` для замены значений
- Исправлены шаблоны писем:
  - Email verification (subject + body)
  - Password reset (subject + body)
  - Welcome email (subject + body)
  - Сообщение об ошибке `ms3_customer_err_invalid_service`

#### 📁 Файлы

```
core/components/minishop3/src/Services/Customer/EmailVerificationService.php
core/components/minishop3/lexicon/ru/customer.inc.php
core/components/minishop3/lexicon/en/customer.inc.php
```

---

### [2026-01-08] 🛒 Адаптивная кнопка корзины в карточках товаров

**Контекст:** Улучшение UX каталога товаров — теперь в карточках товаров отображается актуальное состояние корзины.

---

#### ✨ Добавлено

**Новый UI модуль `ProductCardUI`:**

| Модуль | Файл | Назначение |
|--------|------|------------|
| `ProductCardUI` | `assets/.../js/web/ui/ProductCardUI.js` | Управление состоянием кнопок корзины в карточках товаров |

**Функциональность:**
- При загрузке страницы загружает состояние корзины через API
- Если товар НЕ в корзине — показывает кнопку "В корзину"
- Если товар В корзине — показывает кнопки +/- с полем количества
- Автоматически обновляет все карточки при изменении корзины
- Слушает событие `ms3:cart:updated` для синхронизации

**Новые лексиконы:**

| Ключ | RU | EN |
|------|----|----|
| `ms3_cart_add` | В корзину | Add to cart |
| `ms3_cart_in_cart` | В корзине | In cart |

**Новые SVG иконки:**
- `icon-plus` — кнопка увеличения количества
- `icon-minus` — кнопка уменьшения количества

#### 🔄 Изменено

**Шаблон `ms3_products_row.tpl`:**
- Добавлен атрибут `data-product-id` для связи JS с карточкой
- Добавлен класс `ms3-product-card` для селектора
- Форма добавления: `data-cart-state="add"` (видима по умолчанию)
- Форма изменения: `data-cart-state="change"` (скрыта по умолчанию)

**ms3.js:**
- Добавлена инициализация `productCardUI`

**settings.php:**
- Добавлен `ProductCardUI.js` в список `ms3_frontend_assets`

#### 📁 Файлы

**Новые:**
```
assets/components/minishop3/js/web/ui/ProductCardUI.js
```

**Изменённые:**
```
assets/components/minishop3/js/web/ms3.js
core/components/minishop3/elements/chunks/ms3_products_row.tpl
core/components/minishop3/elements/templates/base.tpl (иконки)
core/components/minishop3/lexicon/ru/cart.inc.php
core/components/minishop3/lexicon/en/cart.inc.php
_build/elements/settings.php
```

#### 💡 Использование в кастомных шаблонах

Для работы функционала в своём шаблоне:

```fenom
<div class="ms3-product-card" data-product-id="{$id}">
    {* Кнопка добавления (видна если НЕ в корзине) *}
    <form class="ms3_form" data-cart-state="add">
        <input type="hidden" name="id" value="{$id}">
        <input type="hidden" name="count" value="1">
        <input type="hidden" name="ms3_action" value="cart/add">
        <button type="submit">{'ms3_cart_add' | lexicon}</button>
    </form>

    {* Кнопки +/- (видны если В корзине) *}
    <form class="ms3_form" data-cart-state="change" style="display: none;">
        <input type="hidden" name="product_key" value="">
        <input type="hidden" name="ms3_action" value="cart/change">
        <button class="qty-btn dec-qty">-</button>
        <input type="number" class="qty-input" name="count" value="1" min="0">
        <button class="qty-btn inc-qty">+</button>
    </form>
</div>
```

---

### [2026-01-05] 👤 CustomerAddressManager — сервис для адресов клиентов

**Контекст:** Минимальный рефакторинг Customer.php — вынесена логика работы с адресами в отдельный сервис.

---

#### ✨ Добавлено

**Новый сервис `CustomerAddressManager`:**

| Сервис | DI ключ | Назначение |
|--------|---------|------------|
| `CustomerAddressManager` | `ms3_customer_address_manager` | CRUD адресов клиентов |

**Методы сервиса:**
- `add(array $addressData)` — добавление адреса с дедупликацией по хэшу
- `getByCustomerId(int $customerId)` — получение всех адресов клиента
- `getById(int $addressId)` — получение адреса по ID
- `update(int $addressId, int $customerId, array $data)` — обновление адреса
- `delete(int $addressId, int $customerId)` — удаление адреса
- `generateHash(array $data)` — генерация хэша для дедупликации

#### 🔄 Изменено

**Customer.php:**
- `addAddress()` → делегирует в `CustomerAddressManager::add()`
- `getAddresses()` → делегирует в `CustomerAddressManager::getByCustomerId()`
- Удалён `generateAddressHash()` (перенесён в сервис)
- Удалены неиспользуемые imports

#### 📁 Файлы

**Новые:**
```
core/components/minishop3/src/Services/Customer/CustomerAddressManager.php  # ~220 строк
```

**Изменённые:**
```
core/components/minishop3/src/Controllers/Customer/Customer.php  # делегирование в сервис
core/components/minishop3/src/ServiceRegistry.php                # +ms3_customer_address_manager
```

---

### [2026-01-05] 🎨 Badge тип колонки и динамические relation поля

**Контекст:** Реализована возможность отображать badge (метку со статусом) в таблицах, используя данные из связанных таблиц через конфигурируемые relation-поля.

---

#### ✨ Добавлено

**Новый тип колонки `badge`:**
- Отображает цветную метку (Tag) со значением из другой колонки
- Настраиваемые параметры:
  - `source_field` — колонка-источник текста (например `status_name`)
  - `color_field` — колонка-источник цвета (например `status_color`)

**Динамические relation поля:**
- Удалены захардкоженные JOIN для Status, Delivery, Payment в OrdersController
- Все связи теперь настраиваются через Grid Config
- Автоматическая группировка relation-полей: несколько полей к одной таблице используют один JOIN
- Метод `extractRelationFields()` в GridConfigService группирует поля по `table + foreignKey`

**Примеры настройки:**
```
1. Создать скрытое relation поле status_name:
   - type: relation
   - table: msOrderStatus
   - foreignKey: status_id
   - displayField: name
   - visible: false

2. Создать скрытое relation поле status_color:
   - type: relation
   - table: msOrderStatus
   - foreignKey: status_id
   - displayField: color
   - visible: false

3. Создать видимое badge поле status:
   - type: badge
   - source_field: status_name
   - color_field: status_color
   - visible: true
```

#### 🐛 Исправлено

- **Badge не отображался:** HEX-цвета в БД хранятся без `#` (например `000000`), а CSS требует `#000000`. Добавлена автоматическая подстановка `#` в `getBadgeColor()`
- **Relation поля терялись при сохранении:** `saveGridConfig()` перезаписывал config вместо merge. Добавлены `relation` и `computed` в `configKeys`
- **Поля показывали неправильный тип после пересохранения:** `loadFields()` не извлекал `relation`/`computed`/`actions` из API ответа
- **Скрытые поля не отображались в конфигураторе:** API `grid-config` не передавал поля с `visible: false`. Добавлен параметр `include_hidden=1` для конфигуратора
- **Query параметры не передавались в контроллеры:** Router.php не объединял `$_GET` с URL pattern vars. Добавлен `array_merge($_GET, $vars)` в `executeRoute()`

#### 📁 Изменённые файлы

- `vueManager/src/components/OrdersGrid.vue` — badge рендеринг, `getBadgeColor()` с `#` префиксом
- `vueManager/src/components/GridFieldsConfig.vue` — UI для badge настройки, `include_hidden=1` при загрузке
- `core/.../Controllers/Api/Manager/OrdersController.php` — динамические JOIN из grid config
- `core/.../Controllers/Api/Manager/GridConfigController.php` — параметр `include_hidden` в `getConfig()`
- `core/.../Services/GridConfigService.php` — `extractRelationFields()`, merge config при сохранении
- `core/.../Router/Router.php` — передача query params в контроллеры

---

### [2026-01-04] 🏗️ Рефакторинг Order.php — сервисная архитектура

**Контекст:** Контроллер Order.php (~1500 строк) разбит на специализированные сервисы. Order.php остаётся фасадом для обратной совместимости, делегируя логику в stateless сервисы. Все сервисы можно переопределить через конфиг.

---

#### ✨ Добавлено

**Новые сервисы в `Services/Order/`:**

| Сервис | DI ключ | Назначение |
|--------|---------|------------|
| `OrderDraftManager` | `ms3_order_draft_manager` | CRUD черновиков заказов |
| `OrderCostCalculator` | `ms3_order_cost_calculator` | Расчёт стоимости |
| `OrderFieldManager` | `ms3_order_field_manager` | CRUD полей + валидация |
| `OrderAddressManager` | `ms3_order_address_manager` | Работа с адресами клиентов |
| `OrderUserResolver` | `ms3_order_user_resolver` | Резолвинг MODX пользователей |
| `OrderSubmitHandler` | `ms3_order_submit_handler` | Оформление заказа |
| `OrderLogService` | `ms3_order_log` | Логирование изменений заказа |
| `OrderStatusService` | `ms3_order_status` | Смена статуса заказа + уведомления |

**Переопределение сервисов (без модификации ядра):**

Создайте `core/config/ms3.services.php`:
```php
<?php
return [
    'ms3_order_submit_handler' => [
        'class' => \MyPackage\Services\CustomOrderSubmitHandler::class,
        'interface' => null,
    ],
];
```

Ваш класс должен наследоваться от оригинального:
```php
<?php
namespace MyPackage\Services;

use MiniShop3\Services\Order\OrderSubmitHandler;

class CustomOrderSubmitHandler extends OrderSubmitHandler
{
    public function submit(...): array
    {
        // Своя логика ДО
        $this->sendToExternalCRM();

        // Вызов родительского метода
        return parent::submit(...);
    }
}
```

---

#### 🔄 Изменено

**Order.php — теперь тонкий фасад (~550 строк вместо ~1500):**
- Все публичные методы сохранены (обратная совместимость)
- Сервисы загружаются из DI с fallback на прямое создание
- Метод `getServiceFromDI()` для получения сервисов

**ServiceRegistry.php:**
- Добавлен метод `registerServiceWithDependencies()` для сервисов с зависимостями
- Все 8 Order сервисов зарегистрированы в DI
- Зависимости между сервисами разрешаются через DI (lazy loading)

**OrderLog.php → OrderLogService.php:**
- Перенесён из `Controllers/Order/` в `Services/Order/`
- Зарегистрирован в DI как `ms3_order_log`
- Добавлен метод `getEntries()` для получения логов заказа
- OrderFieldManager обновлён для использования OrderLogService

**OrderStatus.php → OrderStatusService.php:**
- Перенесён из `Controllers/Order/` в `Services/Order/`
- Зарегистрирован в DI как `ms3_order_status`
- Стандартизирован конструктор: `(modX, MiniShop3, OrderLogService)`
- OrderSubmitHandler обновлён — получает из DI

---

#### 📁 Файлы

**Новые:**
```
core/components/minishop3/src/Services/Order/
├── OrderDraftManager.php      # ~290 строк
├── OrderCostCalculator.php    # ~280 строк
├── OrderFieldManager.php      # ~335 строк
├── OrderAddressManager.php    # ~205 строк
├── OrderUserResolver.php      # ~245 строк
├── OrderSubmitHandler.php     # ~280 строк
├── OrderLogService.php        # ~185 строк (перенесён из Controllers/Order/)
└── OrderStatusService.php     # ~310 строк (перенесён из Controllers/Order/)
```

**Изменённые:**
```
core/components/minishop3/src/Controllers/Order/Order.php       # рефакторинг в фасад + DI
core/components/minishop3/src/Controllers/Cart/Cart.php         # OrderLog → OrderLogService из DI
core/components/minishop3/src/Controllers/Api/Manager/OrdersController.php  # OrderLog → OrderLogService из DI
core/components/minishop3/src/ServiceRegistry.php               # +8 сервисов + dependencies
core/components/minishop3/src/Services/Order/OrderFieldManager.php   # использует OrderLogService
core/components/minishop3/src/Services/Order/OrderSubmitHandler.php  # использует OrderStatusService из DI
core/components/minishop3/src/MiniShop3.php                     # удалён неиспользуемый import OrderStatus
```

**Удалённые:**
```
core/components/minishop3/src/Controllers/Order/OrderLog.php     # → Services/Order/OrderLogService.php
core/components/minishop3/src/Controllers/Order/OrderStatus.php  # → Services/Order/OrderStatusService.php
```

---

### [2026-01-04] 🛒 Рефакторинг Cart.php — сервисная архитектура

**Контекст:** Контроллер Cart.php (~1000 строк) разбит на сервисы по аналогии с Order.php. Cart.php остаётся фасадом для обратной совместимости. Переиспользуется `OrderDraftManager` для общих операций с черновиками.

---

#### ✨ Добавлено

**Новый сервис `CartItemManager`:**

| Сервис | DI ключ | Назначение |
|--------|---------|------------|
| `CartItemManager` | `ms3_cart_item_manager` | CRUD товаров в корзине, валидация, расчёт итогов |

**Методы CartItemManager:**
- `loadItems(msOrder $draft)` — загрузка всех позиций корзины
- `addItem(...)` — добавление товара в корзину
- `updateItemCount(...)` — изменение количества
- `updateItemOptions(...)` — изменение опций товара
- `removeItem(...)` — удаление позиции
- `getItemByKey(...)` — получение позиции по ключу
- `hasItem(...)` / `getItemCount(...)` — проверка наличия
- `validateProduct(int $productId)` — валидация товара
- `validateCount(int $count)` — валидация количества
- `generateProductKey(...)` — генерация уникального ключа
- `normalizeOptions(...)` — нормализация опций
- `calculateStatus(...)` — расчёт итогов корзины

---

#### 🔄 Изменено

**OrderDraftManager.php — расширен для совместного использования с Cart:**

Добавлены 7 методов (shared layer между Cart и Order):
```php
attachCustomer(msOrder $draft, int $customerId): bool
ensureAddress(msOrder $draft): msOrderAddress
syncToken(msOrder $draft, string $newToken): bool
getDraftByCustomer(int $customerId, string $ctx = 'web'): ?msOrder
deleteDraft(msOrder $draft): bool
isEmpty(msOrder $draft): bool
getSessionCustomerId(): int
```

**Cart.php — теперь тонкий фасад (~650 строк вместо ~1000):**
- Все публичные методы сохранены (обратная совместимость)
- Использует `OrderDraftManager` из DI для управления черновиками
- Использует `CartItemManager` из DI для операций с товарами
- Использует `OrderLogService` из DI для логирования
- Метод `getServiceFromDI()` для получения сервисов с fallback

**ServiceRegistry.php:**
- Добавлена регистрация `ms3_cart_item_manager`
- CartItemManager получает modX и MiniShop3 через конструктор

---

#### 📁 Файлы

**Новые:**
```
core/components/minishop3/src/Services/Cart/
└── CartItemManager.php     # ~410 строк
```

**Изменённые:**
```
core/components/minishop3/src/Services/Order/OrderDraftManager.php  # +7 shared методов (~145 строк)
core/components/minishop3/src/Controllers/Cart/Cart.php             # рефакторинг в фасад + DI
core/components/minishop3/src/ServiceRegistry.php                   # +1 сервис
```

---

#### 🎯 Архитектурные решения

**Почему не создали отдельный `CartDraftManager`:**
- Корзина и заказ используют одну и ту же модель `msOrder` с разными статусами
- Draft (черновик) — это заказ со статусом `ms3_status_draft`
- Общая логика: получение/создание черновика, синхронизация токена, привязка покупателя
- Избежали дублирования кода: Cart напрямую использует `OrderDraftManager`

**Разделение ответственности:**
```
OrderDraftManager    — жизненный цикл черновика (общий)
CartItemManager      — операции с позициями корзины (Cart-specific)
OrderCostCalculator  — расчёт стоимости заказа (Order-specific)
OrderFieldManager    — поля заказа (Order-specific)
```

---

### [2026-01-03] 🛡️ Проверка зависимости ModxProVueCore

**Контекст:** Реализована проверка наличия ModxProVueCore при загрузке страниц админки. Вместо ошибок в консоли при отсутствии зависимости теперь показывается понятный MODX алерт с инструкцией по установке.

---

#### ✨ Добавлено

**1. Метод `addVueModule()` в базовых контроллерах:**
- Регистрирует Vue ES module с проверкой зависимости ModxProVueCore
- Добавляет атрибут `data-vue-module` для идентификации скриптов
- Автоматически добавляет версию для сброса кэша

**2. Метод `registerVueCoreCheck()` — inline скрипт проверки:**
- Ищет `<script type="importmap">` с ключом `vue`
- При отсутствии: удаляет все Vue module скрипты
- Показывает `MODx.msg.alert()` с сообщением об установке
- Устанавливает глобальный флаг `window.MS3_VUE_CORE_MISSING = true`

**3. Лексиконы для сообщения об ошибке:**
```php
// ru/default.inc.php
$_lang['ms3_error'] = 'Ошибка';
$_lang['ms3_modxprovuecore_required'] = 'Для работы MiniShop3 требуется пакет ModxProVueCore...';

// en/default.inc.php
$_lang['ms3_error'] = 'Error';
$_lang['ms3_modxprovuecore_required'] = 'ModxProVueCore package is required for MiniShop3...';
```

---

#### 🔄 Изменено

**Все контроллеры переведены на `addVueModule()`:**

| Контроллер | Vue модули |
|------------|------------|
| `mgr/utilities.class.php` | 6 модулей (fields-management, extra-fields, grid-fields-config, model-fields, import, utilities-gallery) |
| `mgr/settings.class.php` | 5 модулей (deliveries, payments, vendors, statuses, links) |
| `mgr/orders.class.php` | 1 модуль (orders) |
| `mgr/order.class.php` | 1 модуль (order) |
| `mgr/customers.class.php` | 1 модуль (customers) |
| `mgr/notifications.class.php` | 1 модуль (notifications) |
| `category/update.class.php` | 1 модуль (category-products) |
| `product/update.class.php` | 2 модуля (gallery-uploader, main) |

---

#### 📁 Файлы

**Изменённые (базовые контроллеры):**
```
core/components/minishop3/controllers/
├── manager.class.php           # +addVueModule(), +registerVueCoreCheck()
├── resource_update.class.php   # +addVueModule(), +registerVueCoreCheck()
└── resource_create.class.php   # +addVueModule(), +registerVueCoreCheck()
```

**Изменённые (mgr контроллеры):**
```
core/components/minishop3/controllers/mgr/
├── utilities.class.php         # regClientStartupHTMLBlock → addVueModule
├── settings.class.php          # regClientStartupHTMLBlock → addVueModule
├── orders.class.php            # regClientStartupHTMLBlock → addVueModule
├── order.class.php             # regClientStartupHTMLBlock → addVueModule
├── customers.class.php         # regClientStartupHTMLBlock → addVueModule
└── notifications.class.php     # regClientStartupHTMLBlock → addVueModule
```

**Изменённые (resource контроллеры):**
```
core/components/minishop3/controllers/
├── category/update.class.php   # regClientStartupHTMLBlock → addVueModule
└── product/update.class.php    # regClientStartupHTMLBlock → addVueModule (2 места)
```

**Изменённые (лексиконы):**
```
core/components/minishop3/lexicon/
├── ru/default.inc.php          # +ms3_error, +ms3_modxprovuecore_required
└── en/default.inc.php          # +ms3_error, +ms3_modxprovuecore_required
```

---

#### 📚 Документация

Обновлён `D:\development\modxpro-vue-core\DEVELOPER_GUIDE.md`:
- Добавлен раздел "Проверка наличия ModxProVueCore"
- Полный пример реализации `addVueModule()` для других компонентов
- Обновлён чеклист интеграции

---

### [2026-01-01] 📦 CategoryProductsGrid: Действие копирования и динамические фильтры

**Контекст:** Завершение работы над Vue-компонентом списка товаров категории. Добавлено действие копирования товара, реализованы динамические фильтры на основе конфигурации грида.

---

#### ✨ Добавлено

**1. Действие "Копировать товар" (duplicate):**
- Кнопка копирования в колонке действий
- Использует стандартное MODX окно `modx-window-resource-duplicate`
- После копирования обновляется дерево ресурсов и список товаров

**Файлы:**
```
vueManager/src/
├── actionRegistry.js              # Обработчик 'duplicate'
├── composables/useActions.js      # Callback onDuplicate
├── components/ActionsColumn.vue   # Emit 'duplicate'
└── components/CategoryProductsGrid.vue  # Функция duplicateProduct()

core/.../lexicon/
├── ru/vue.inc.php                 # duplicate, product_duplicated
└── en/vue.inc.php
```

**2. Динамические фильтры из конфигурации грида:**
- Фильтры генерируются автоматически на основе полей с `filterable = true`
- Автоопределение типа: boolean поля → select (Да/Нет), остальные → text
- Статический конфиг (`config/filters/*.php`) используется для переопределений

**3. Метод `GridConfigService::getFilterableFields()`:**
```php
// Возвращает поля с filterable = true для указанного грида
$filters = $gridConfigService->getFilterableFields('category-products');
```

**4. Обработка фильтров в CategoryProductsController:**
- **Текстовые** (LIKE): `pagetitle`, `longtitle`, `alias`, `article`, `made_in`
- **Boolean** (exact): `published`, `deleted`, `new`, `popular`, `favorite`
- **Числовые** (exact): `price`, `old_price`, `weight`, `vendor_id`

---

#### 🐛 Исправлено

**1. Пустые опции в select-фильтрах:**
- Проблема: select фильтры были пустыми (опции не резолвились)
- Решение: вызов `getFilters()` с `resolveOptions = true`

**2. Перевод лексиконов в опциях:**
- Добавлен метод `resolveStaticOptions()` для перевода ключей `ms3_yes`, `ms3_no`
- Загрузка лексикона `minishop3:vue` в конструкторе FilterConfigManager

---

#### 📁 Файлы

**Изменённые:**
```
core/components/minishop3/
├── bootstrap.php                           # Сервис ms3_grid_config
├── src/Services/GridConfigService.php      # getFilterableFields()
├── src/Services/FilterConfigManager.php    # Динамические фильтры
└── src/Controllers/Api/Manager/CategoryProductsController.php  # Обработка фильтров
```

---

## Декабрь 2025

### [2025-12-27] 🔌 Система событий: Plugin Chaining, Customer события, оптимизация

**Контекст:** Комплексный аудит и улучшение системы событий плагинов MiniShop3. Исправлена проблема с цепочкой плагинов, добавлены новые Customer события, оптимизирована производительность.

---

#### 🐛 Исправлено

**1. PHP Deprecated Warning:**
- Исправлен `msProductFile.php:201` — параметр `$options` теперь обязательный в методе `makeThumbnail(array $options, array $info)`

**2. Проблема с цепочкой плагинов (Plugin Chaining):**
- Раньше: несколько плагинов на одном событии перезаписывали данные друг друга
- Решение: паттерн `$modx->eventData[$eventName]` для передачи данных между плагинами
- Затронутые методы в `ProductDataService.php`:
  - `getModifiedPrice()` — модификация цены товара
  - `getModifiedWeight()` — модификация веса товара
  - `getModifiedFields()` — модификация полей товара

**Пример использования цепочки плагинов:**
```php
// Плагин 1: Скидка 10%
case 'msOnGetProductPrice':
    $price = $modx->eventData['msOnGetProductPrice']['price'] ?? $scriptProperties['price'];
    $newPrice = $price * 0.9;
    $modx->eventData['msOnGetProductPrice']['price'] = $newPrice;
    break;

// Плагин 2: Округление (получит уже скидочную цену!)
case 'msOnGetProductPrice':
    $price = $modx->eventData['msOnGetProductPrice']['price'] ?? $scriptProperties['price'];
    $newPrice = round($price, -1);
    $modx->eventData['msOnGetProductPrice']['price'] = $newPrice;
    break;
```

---

#### ✨ Добавлено

**1. Customer события (9 шт.):**

| Метод | События |
|-------|---------|
| `add()` | `msOnBeforeAddToCustomer`, `msOnAddToCustomer` |
| `validate()` | `msOnBeforeValidateCustomerValue`, `msOnValidateCustomerValue`, `msOnErrorValidateCustomerValue` |
| `create()` | `msOnBeforeCreateCustomer`, `msOnCreateCustomer` |
| `addAddress()` | `msOnBeforeAddCustomerAddress`, `msOnAddCustomerAddress` |

**2. Отсутствующие события в events.php (6 шт.):**
- `msOnErrorValidateOrderValue` — обработка ошибок валидации заказа
- `msOnBeforeGetOrderUser` / `msOnGetOrderUser` — получение пользователя заказа
- `msOnBeforeImport` / `msOnAfterImport` / `msOnImportRow` — импорт данных

---

#### ⚡ Оптимизация

**Early return для событий без плагинов:**
```php
// Если для события нет зарегистрированных плагинов — вызов invokeEvent() пропускается
if (empty($this->modx->eventMap[$eventName])) {
    return $price;
}
```

---

#### 📁 Файлы

**Изменённые:**
```
core/components/minishop3/
├── src/Model/msProductFile.php                    # PHP Deprecated fix
├── src/Services/Product/ProductDataService.php   # Plugin chaining + optimization
└── src/Controllers/Customer/Customer.php         # 9 новых событий

_build/elements/events.php                         # +15 событий (всего 70)
```

---

#### 📊 Статистика событий

| Категория | Количество |
|-----------|------------|
| Cart events | 15 |
| Order events | 25 |
| Order product events | 6 |
| Order user events | 2 |
| Order customer events | 2 |
| **Customer events** | **9** ✨ |
| Delivery & Payment events | 4 |
| Product events | 3 |
| Vendor events | 6 |
| Import events | 3 |
| Notification events | 3 |
| Manager events | 1 |
| **Итого** | **70** |

---

### [2025-12-24] 🔧 Улучшения гридов настроек: сортировка, изображения, конфигурация производителей

**Контекст:** Улучшена функциональность вкладок настроек - добавлена сортировка перетаскиванием через vuedraggable, поддержка колонок с изображениями, конфигурация полей производителей.

---

#### ✨ Добавлено

**1. Drag-drop сортировка через vuedraggable:**
- StatusesGrid, VendorsGrid, DeliveriesGrid, PaymentsGrid переведены на vuedraggable
- Заменены PrimeVue DataTable с rowReorder на кастомные таблицы с vuedraggable
- Добавлены API endpoints для сортировки:
  - `POST /api/mgr/vendors/sort` - сортировка производителей
  - `POST /api/mgr/deliveries/sort` - сортировка способов доставки
  - `POST /api/mgr/payments/sort` - сортировка способов оплаты

**2. Поддержка колонки типа `image` в гридах:**
- VendorsGrid, DeliveriesGrid, PaymentsGrid поддерживают отображение изображений
- Функция `normalizeImagePath()` - нормализация пути (добавление `/` в начало)
- Миниатюра 40x40px с `object-fit: contain`

**3. Типы полей в GridFieldsConfig:**
- Добавлен тип `image` - для отображения изображений
- Добавлен тип `boolean` - для отображения да/нет

**4. msVendor в Model Fields Configuration:**
- Производители добавлены в конфигурацию полей моделей
- Миграция `20251223120000_seed_vendor_model_fields.php` - поля производителя
- Миграция `20251223130000_seed_vendors_grid_config.php` - конфигурация грида
- Секции: `vendor_info` (Информация), `vendor_address` (Адрес)

**5. Лексиконы (RU/EN):**
- `vendor_order_saved` - "Порядок производителей сохранён"
- `status_order_saved` - "Порядок статусов сохранён"
- `delivery_order_saved` - "Порядок способов доставки сохранён"
- `payment_order_saved` - "Порядок способов оплаты сохранён"
- `field_type_image` - "Изображение"
- `field_type_boolean` - "Логическое (да/нет)"
- Лексиконы для полей msVendor (`ms3_vendor_name`, `ms3_vendor_logo`, etc.)

---

#### 🔄 Изменено

**1. VendorsGrid.vue:**
- Диалог редактирования теперь строится из секций (как StatusesGrid)
- Убрана жёстко закодированная структура вкладок
- Поля группируются по секциям из msModelFieldSection

**2. Архитектура сортировки:**
- Переход от PrimeVue `rowReorder` к библиотеке vuedraggable
- Единообразный подход во всех гридах настроек
- Handle-based перетаскивание (иконка ≡)

---

#### 📁 Файлы

**Новые:**
```
core/components/minishop3/migrations/
├── 20251223120000_seed_vendor_model_fields.php    # Поля модели msVendor
└── 20251223130000_seed_vendors_grid_config.php    # Конфиг грида производителей
```

**Изменённые:**
```
core/components/minishop3/
├── config/routes/manager.php                      # Роуты сортировки
├── lexicon/ru/vue.inc.php                         # Лексиконы
├── lexicon/en/vue.inc.php                         # Лексиконы
└── src/Controllers/Api/Manager/
    ├── VendorsController.php                      # Метод sort()
    ├── DeliveriesController.php                   # Метод sort()
    └── PaymentsController.php                     # Метод sort()

vueManager/src/components/
├── StatusesGrid.vue                               # vuedraggable
├── VendorsGrid.vue                                # vuedraggable + image + sections
├── DeliveriesGrid.vue                             # vuedraggable + image
├── PaymentsGrid.vue                               # vuedraggable + image
└── GridFieldsConfig.vue                           # Типы image, boolean
```

---

### [2025-12-22] ⚙️ Полная миграция раздела "Настройки" на Vue + Очистка ExtJS

**Контекст:** Завершена миграция всех вкладок раздела "Настройки" с ExtJS на Vue 3 + PrimeVue (кроме "Опции"). Удалён устаревший ExtJS код.

---

#### ✨ Добавлено

**1. Vue компонент DeliveriesGrid (Способы доставки):**
- DataTable со списком способов доставки
- Создание/редактирование/удаление способов доставки
- Drag-drop сортировка (rank)
- Управление привязкой способов оплаты к доставке
- Настройка весовых ограничений (weight_from, weight_to)
- Настройка ограничений по сумме заказа (order_cost_from, order_cost_to)
- Настройка расстояния (distance_from, distance_to)
- Выбор класса обработчика доставки
- Логотип доставки через FileBrowser
- Массовое удаление с подтверждением

**2. Vue компонент PaymentsGrid (Способы оплаты):**
- DataTable со списком способов оплаты
- Создание/редактирование/удаление способов оплаты
- Drag-drop сортировка (rank)
- Управление привязкой способов доставки к оплате
- Выбор класса обработчика оплаты
- Логотип оплаты через FileBrowser
- Массовое удаление с подтверждением

**3. Vue компонент StatusesGrid (Статусы заказов):**
- DataTable со списком статусов заказов
- Создание/редактирование/удаление статусов
- Drag-drop сортировка (rank)
- Цветовая индикация статуса (ColorPicker)
- Чекбоксы: active, final, fixed, notify_user, notify_manager
- Email-шаблоны для уведомлений (chunk_user, chunk_manager)
- Поддержка лексиконов для имён статусов
- Массовое удаление с подтверждением

**4. Vue компонент VendorsGrid (Производители):**
- DataTable со списком производителей
- Создание/редактирование/удаление производителей
- Drag-drop сортировка (rank)
- Логотип производителя через FileBrowser
- Связь с ресурсом MODX
- Страна, email, телефон, адрес, описание
- Массовое удаление с подтверждением

**5. Vue компонент LinksGrid (Связи товаров):**
- DataTable со списком типов связей товаров
- Создание/редактирование/удаление типов связей
- Тип связи нельзя изменить после создания (readonly)
- Описание типа отображается при выборе в Select
- Типы: one_to_one, one_to_many, many_to_one, many_to_many
- Массовое удаление с подтверждением

**6. API контроллеры:**
- `DeliveriesController` - CRUD + getHandlers, getPayments, updatePayments
- `PaymentsController` - CRUD + getHandlers, getDeliveries, updateDeliveries
- `StatusesController` - CRUD + updateRank
- `VendorsController` - CRUD
- `LinksController` - CRUD + getTypes

**7. Роуты для всех сущностей:**
```php
// Deliveries
$router->group('/deliveries', function($router) {
    $router->get('', ...);
    $router->post('', ...);
    $router->get('/handlers', ...);
    $router->delete('/bulk', ...);
    $router->get('/{id}', ...);
    $router->put('/{id}', ...);
    $router->delete('/{id}', ...);
    $router->get('/{id}/payments', ...);
    $router->put('/{id}/payments', ...);
});

// Payments, Statuses, Vendors, Links - аналогично
```

**8. Новые лексиконы (RU/EN):**
- Deliveries: `delivery_create`, `delivery_edit`, `delivery_payments`, etc.
- Payments: `payment_create`, `payment_edit`, `payment_deliveries`, etc.
- Statuses: `status_create`, `status_edit`, `status_color`, etc.
- Vendors: `vendor_create`, `vendor_edit`, etc.
- Links: `link_create`, `link_edit`, `link_type_readonly`, `select_type`, etc.

---

#### 🐛 Исправлено

**1. StatusesGrid - имя статуса не переводилось:**
- Добавлена функция `getDisplayName()` для отображения лексикона вместо ключа

**2. StatusesGrid - стили диалога не применялись:**
- Dialog рендерится через teleport в body, вне `.vueApp` контейнера
- Перенесены стили в глобальный блок с префиксом `.ms3-status-form`

**3. StatusesGrid - drag-drop не работал:**
- Исправлен синтаксис: `rowReorder` вместо `:rowReorder="true"`
- Удалён дублирующий атрибут `:reorderableRows`

---

#### 🗑️ Удалено

**ExtJS файлы настроек (мигрированы на Vue):**
```
assets/components/minishop3/js/mgr/settings/
├── delivery/           # 3 файла (grid.js, window.js, members.js)
├── payment/            # 3 файла (grid.js, window.js, members.js)
├── vendor/             # 2 файла (grid.js, window.js)
├── status/             # 2 файла (grid.js, window.js)
└── link/               # 2 файла (grid.js, window.js)
```

**Закомментированный код:**
- Удалены legacy комментарии из `settings.class.php`

**PHP конфигурация для ExtJS (устарела):**
```
core/components/minishop3/src/Controllers/Config/Settings/
├── Layout.php              # Формировал конфиг для ExtJS
├── Delivery/
│   ├── Grid.php           # Колонки ExtJS грида доставок
│   └── Window.php         # Поля ExtJS окна доставок
└── Vendor/
    ├── Grid.php           # Колонки ExtJS грида производителей
    └── Window.php         # Поля ExtJS окна производителей
```
- Удалено использование Layout из `settings.class.php`
- Удалена передача `ms3.config.layout` в JavaScript

---

#### 📁 Файлы

**Новые:**
```
core/components/minishop3/src/Controllers/Api/Manager/
├── DeliveriesController.php               # API контроллер доставок
├── PaymentsController.php                 # API контроллер оплат
├── StatusesController.php                 # API контроллер статусов
├── VendorsController.php                  # API контроллер производителей
└── LinksController.php                    # API контроллер связей

vueManager/src/
├── components/
│   ├── DeliveriesGrid.vue                 # Vue компонент доставок
│   ├── PaymentsGrid.vue                   # Vue компонент оплат
│   ├── StatusesGrid.vue                   # Vue компонент статусов
│   ├── VendorsGrid.vue                    # Vue компонент производителей
│   └── LinksGrid.vue                      # Vue компонент связей
└── entries/
    ├── deliveries.js                      # Entry point доставок
    ├── payments.js                        # Entry point оплат
    ├── statuses.js                        # Entry point статусов
    ├── vendors.js                         # Entry point производителей
    └── links.js                           # Entry point связей
```

**Изменённые:**
```
core/components/minishop3/
├── config/routes/manager.php              # Роуты для всех сущностей настроек
├── controllers/mgr/settings.class.php     # Загрузка Vue assets, удалён legacy код
└── lexicon/
    ├── ru/vue.inc.php                     # Лексиконы для всех компонентов
    └── en/vue.inc.php                     # Лексиконы для всех компонентов

vueManager/vite.config.js                  # Entry points для всех компонентов

assets/components/minishop3/js/mgr/settings/
└── settings.panel.js                      # Vue контейнеры вместо ExtJS
```

---

#### 📊 Статус миграции настроек

| Вкладка | Статус | Компонент |
|---------|--------|-----------|
| Доставки | ✅ Vue | DeliveriesGrid.vue |
| Оплаты | ✅ Vue | PaymentsGrid.vue |
| Статусы | ✅ Vue | StatusesGrid.vue |
| Производители | ✅ Vue | VendorsGrid.vue |
| Связи | ✅ Vue | LinksGrid.vue |
| Опции | ⏳ ExtJS | option/*.js |

---

### [2025-12-22] 🔍 Массовое выделение строк для Клиентов и Заказов

**Контекст:** Добавлена универсальная система выделения строк для Vue DataTables. Реализовано для страниц Клиенты и Заказы.

---

#### ✨ Добавлено

**1. Composable `useSelection.js` для массового выделения:**
- Универсальное управление выделением строк в любых DataTable
- Подсчёт выбранных элементов
- Bulk delete с подтверждением через ConfirmDialog
- Поддержка кастомных обработчиков удаления (одиночное/массовое)

```javascript
// Пример использования
const {
  selectedItems,
  hasSelection,
  selectionCount,
  confirmBulkDelete
} = useSelection({
  entityName: 'order',
  deleteBulk: async (ids) => await request.delete('/api/mgr/orders/bulk', { ids }),
  onSuccess: () => loadOrders(),
  getItemName: (item) => `#${item.num || item.id}`
})
```

**2. Bulk delete endpoints:**
- `DELETE /api/mgr/customers/bulk` — массовое удаление клиентов
- `DELETE /api/mgr/orders/bulk` — массовое удаление заказов (с каскадным удалением адресов, товаров, логов)
- Принимают массив `ids`, возвращают количество удалённых/проваленных

**3. UI элементы массового выделения:**
- CustomersGrid: колонка с чекбоксами, тулбар с действиями
- OrdersGrid: колонка с чекбоксами, тулбар с действиями
- Диалог подтверждения массового удаления

**4. Новые лексиконы (RU/EN):**
- `selected_count`, `clear_selection`, `delete_selected`
- `bulk_delete_confirm_title`, `bulk_delete_confirm_message`, `bulk_delete_success`
- `apply_filters`, `clear_filters`

---

#### 📁 Изменённые файлы

```
vueManager/src/
├── composables/
│   └── useSelection.js                # NEW: Универсальный composable для выделения
└── components/
    ├── CustomersGrid.vue              # Интеграция выделения и локализация фильтров
    └── OrdersGrid.vue                 # Интеграция выделения

core/components/minishop3/
├── src/Controllers/Api/Manager/
│   ├── CustomersController.php        # Метод bulkDelete()
│   └── OrdersController.php           # Метод bulkDelete() (с каскадным удалением)
├── config/routes/
│   └── manager.php                    # Роуты DELETE /api/mgr/*/bulk
└── lexicon/
    ├── ru/vue.inc.php                 # Лексиконы для bulk операций
    └── en/vue.inc.php                 # Лексиконы для bulk операций
```

---

### [2025-12-22] ✏️ Редактирование секций в управлении свойствами товара

**Контекст:** Добавлена возможность редактирования секций на странице Утилиты → Управление свойствами товара.

---

#### ✨ Добавлено

**1. Кнопка редактирования секции:**
- Иконка карандаша в таблице секций рядом с кнопкой удаления
- Открывает диалог редактирования при клике

**2. Диалог редактирования секции:**
- Ключ секции (только для чтения)
- Ключ лексикона (для мультиязычности)
- Прямой текст подписи (label)
- Чекбокс видимости секции

**3. Новые лексиконы (RU/EN):**
- `section_edit`, `edit_section_title`, `section_key_readonly_hint`, `section_updated`, `save_button`

---

#### 🐛 Исправлено

**1. Сохранение секций не работало:**
- `isset()` не обрабатывал `null` значения из JSON — заменено на `array_key_exists()`
- Config не перезаписывался если был пустым — теперь всегда обновляется

**2. Кастомный label игнорировался:**
- Лексикон имел приоритет над явно заданным label
- Изменён приоритет: 1) кастомный label, 2) лексикон, 3) section_key

---

#### 📁 Изменённые файлы

```
vueManager/src/components/
└── ProductDataConfig.vue              # Диалог и логика редактирования секций

core/components/minishop3/
├── src/Services/
│   └── ConfigService.php              # Исправлено сохранение и чтение секций
└── lexicon/
    ├── ru/vue.inc.php                 # Новые лексиконы
    └── en/vue.inc.php                 # Новые лексиконы
```

---

### [2025-12-21] 🔄 Миграция вкладки "Галерея" на Vue

**Контекст:** Замена ExtJS интерфейса на Vue 3 + PrimeVue для вкладки "Галерея" в утилитах. Сохранена полная функциональность регенерации миниатюр.

---

#### ✨ Добавлено

**1. Vue компонент UtilitiesGallery:**
- Информационная карточка с данными о Media Source и количестве файлов
- Сворачиваемая секция с настройками миниатюр
- Настройка количества продуктов за шаг (1-100)
- Progress bar с процентами и итерациями
- Кнопка Reset после завершения

**2. API роут для галереи:**
- `POST /api/mgr/utilities/gallery/update` — регенерация миниатюр
- Middleware проверки прав `msproductfile_generate`

---

#### 🔄 Изменено

**1. utilities.panel.js:**
- Вкладка "Галерея" теперь монтирует Vue компонент вместо ExtJS

**2. utilities.class.php:**
- Загрузка CSS/JS для Vue компонента галереи
- Убрана загрузка старого ExtJS файла `gallery/panel.js`

---

#### 📁 Созданные/изменённые файлы

```
vueManager/src/
├── components/
│   └── UtilitiesGallery.vue           # Новый Vue компонент
├── entries/
│   └── utilities-gallery.js            # Entry point для сборки
└── vite.config.js                      # Добавлен entry point

core/components/minishop3/
├── config/routes/
│   └── manager.php                     # Добавлен API роут /utilities/gallery/update
└── controllers/mgr/
    └── utilities.class.php             # Загрузка Vue CSS/JS

assets/components/minishop3/js/mgr/
├── utilities/
│   └── utilities.panel.js              # Интеграция Vue в ExtJS tab
└── vue-dist/
    ├── utilities-gallery.min.js        # Собранный Vue компонент
    └── utilities-gallery.min.css       # Стили компонента
```

---

### [2025-12-19] 📦 Рефакторинг импорта из CSV (PR #51)

**Контекст:** Полностью переработан интерфейс и backend импорта товаров из CSV. Добавлен Vue компонент с пошаговым wizard-интерфейсом.

---

#### ✨ Добавлено

**1. Vue компонент ImportProducts:**
- Пошаговый интерфейс (3 шага): загрузка файла → маппинг полей → импорт
- Drag & drop загрузка CSV файлов
- Автоматическое определение кодировки (UTF-8, Windows-1251, etc.)
- Предпросмотр данных перед импортом
- Автоматический маппинг полей по названиям колонок
- Индикаторы прогресса и статистика результатов

**2. Новые процессоры:**
- `Import/Upload.php` — загрузка CSV файла
- `Import/Preview.php` — предпросмотр и анализ CSV
- `Import/Fields.php` — получение доступных полей для маппинга
- `Import/Progress.php` — отслеживание прогресса импорта

**3. Улучшения ImportCSV.php:**
- Автоопределение и конвертация кодировки
- Поддержка различных разделителей (`;`, `,`, `Tab`)
- Улучшенный парсинг с обработкой кавычек
- Валидация данных перед импортом

**4. Конфигурация полей импорта:**
- Новый файл `config/import-fields.php` с описанием всех полей
- Поддержка связей (vendor, parent) с автопоиском

---

#### 📁 Ключевые файлы

```
core/components/minishop3/
├── config/
│   └── import-fields.php                    # Конфигурация полей
├── src/
│   ├── Processors/Utilities/Import/
│   │   ├── Upload.php                       # Загрузка файла
│   │   ├── Preview.php                      # Предпросмотр
│   │   ├── Fields.php                       # Список полей
│   │   └── Progress.php                     # Прогресс
│   └── Utils/
│       └── ImportCSV.php                    # Ядро импорта

vueManager/src/
├── components/
│   └── ImportProducts.vue                   # Vue компонент
└── entries/
    └── import.js                            # Entry point
```

---

### [2025-12-17] 🔧 Улучшения страницы заказов и фильтров

**Контекст:** Исправлены фильтры на странице списка заказов, добавлена навигация между заказами и клиентами, улучшена статистика заказов.

---

#### ✨ Добавлено

**1. Автооткрытие клиента из заказа:**
- Ссылка на клиента в таблице заказов ведёт на страницу клиентов с параметром `customer_id`
- При открытии страницы клиентов с параметром `customer_id` автоматически открывается диалог редактирования клиента

**2. Статистика с учётом фильтров:**
- Итоговые цифры (количество заказов, сумма) теперь пересчитываются при применении фильтров
- Применяются все активные фильтры: статус, доставка, оплата, даты

---

#### 🐛 Исправлено

**1. Фильтры-селекты не показывали варианты:**
- Исправлено поле сортировки: `rank` → `position`
- Исправлено значение active: `true` → `1` (числовое значение в БД)

**2. Фильтр по дате не работал:**
- Заменён `andCondition()` на `where()` для корректной работы фильтра

**3. Ссылка на клиента:**
- Исправлен параметр URL: `customer` → `customer_id`

---

#### 🔄 Изменено

**1. Статус по умолчанию при создании заказа:**
- Изменён с ID=2 (Новый) на ID=1 (Черновик)

**2. Лексикон статистики заказов:**
- `orders_month`: "За месяц" → "Заказов"
- `orders_month_sum`: "Сумма" → "На сумму"

---

#### 📁 Изменённые файлы

```
core/components/minishop3/
├── config/filters/
│   └── orders.php                           # Исправлены поля sort и where
├── src/Controllers/Api/Manager/
│   └── OrdersController.php                 # getOrdersStats() с фильтрами
└── lexicon/
    ├── ru/vue.inc.php                       # orders_month, orders_month_sum
    └── en/vue.inc.php                       # orders_month, orders_month_sum

vueManager/src/components/
├── OrdersGrid.vue                           # Исправлена ссылка customer_id
├── OrderView.vue                            # Статус по умолчанию = 1
└── CustomersGrid.vue                        # Автооткрытие клиента из URL
```

---

### [2025-12-15] 🏗️ Управление полями моделей (Model Fields)

**Контекст:** Добавлена новая подсистема для управления видимостью и настройками полей в формах админки. Позволяет настраивать какие поля показывать в формах заказов, клиентов и других сущностей.

---

#### ✨ Добавлено

**1. Новые модели БД:**
- `msModelField` — настройки полей моделей (видимость, порядок, xtype)
- `msModelFieldSection` — секции для группировки полей

**2. Vue компонент ModelFieldsGrid:**
- Управление полями через drag & drop
- Группировка полей по секциям
- Настройка видимости, порядка, xtype для каждого поля
- Создание/редактирование/удаление секций

**3. API контроллер ModelFieldsController:**
- CRUD операции для полей и секций
- Получение полей с учётом видимости
- Массовое обновление порядка (ranks)
- Получение опций для комбобоксов

**4. Сервис ComboConfigManager:**
- Централизованная конфигурация комбобоксов
- Загрузка опций из JSON файлов (`config/combos/`)
- Поддержка динамических данных из БД

**5. Миграции БД:**
- `create_model_fields_table` — таблица полей
- `create_model_field_sections_table` — таблица секций
- `seed_model_fields` — начальные данные полей
- `seed_model_field_sections` — начальные секции

---

#### 🔄 Изменено

**1. Удалены старые ExtJS компоненты:**
- `customers/*.js` — старые гриды клиентов
- `orders/*.js` — старые формы заказов

**2. OrderView.vue:**
- Переработан с использованием конфигурации полей
- Динамическое построение формы по настройкам

---

#### 📁 Ключевые файлы

```
core/components/minishop3/
├── config/combos/
│   ├── msOrder.php                          # Комбобоксы заказа
│   └── msOrderAddress.php                   # Комбобоксы адреса
├── src/
│   ├── Controllers/Api/Manager/
│   │   └── ModelFieldsController.php        # API контроллер
│   ├── Model/
│   │   ├── msModelField.php                 # Модель поля
│   │   └── msModelFieldSection.php          # Модель секции
│   └── Services/
│       └── ComboConfigManager.php           # Сервис комбобоксов
└── migrations/
    ├── 20251205120000_create_model_fields_table.php
    ├── 20251205120100_seed_model_fields.php
    ├── 20251211120000_create_model_field_sections_table.php
    └── 20251211120200_seed_model_field_sections.php

vueManager/src/
├── components/
│   ├── ModelFieldsGrid.vue                  # Управление полями
│   └── OrderView.vue                        # Обновлённая форма заказа
└── entries/
    └── model-fields.js                      # Entry point
```

---

### [2025-12-16] ✨ Система логирования заказов (msOrderLog)

**Контекст:** Расширена система логирования заказов с поддержкой разных типов действий, JSON-формата записей и интеграцией во фронтенд контроллеры.

---

#### ✨ Добавлено

**1. Типы действий логирования:**
```php
// Константы в msOrderLog
msOrderLog::ACTION_STATUS   = 'status'   // Смена статуса
msOrderLog::ACTION_PAYMENT  = 'payment'  // Платежи/возвраты
msOrderLog::ACTION_PRODUCTS = 'products' // Изменение состава заказа
msOrderLog::ACTION_ADDRESS  = 'address'  // Изменение адреса
msOrderLog::ACTION_FIELD    = 'field'    // Изменение полей заказа
```

**2. Системная настройка `ms3_order_log_actions`:**
- Управляет типами логируемых действий
- Значение по умолчанию: `status,products,field,address`
- Значение `*` — логировать всё
- Пустое значение — отключить логирование

**3. JSON формат записей:**
```json
// action: products (добавление)
{
  "operation": "add",
  "product_id": 123,
  "product_name": "iPhone 15",
  "count": 2,
  "price": 99900,
  "cost": 199800
}

// action: products (обновление)
{
  "operation": "update",
  "product_id": 123,
  "product_name": "iPhone 15",
  "changes": {
    "count": {"old": 1, "new": 2}
  }
}

// action: field/address
{
  "fields": {
    "delivery_id": {"old": 1, "new": 2},
    "city": {"old": "Москва", "new": "СПб"}
  }
}
```

**4. Интеграция логирования во фронтенд:**
- **Cart.php** — логирование add(), change(), remove() товаров в корзине
- **Order.php** — логирование изменения полей и адреса при оформлении

**5. Миграция БД:**
- Поле `entry` изменено с VARCHAR(255) на TEXT для JSON
- Добавлено поле `visible` (BOOLEAN) для управления видимостью клиенту

**6. Форматирование вывода в Vue:**
- GetLog процессор формирует `entry_formatted` для отображения
- Vue компонент отображает читаемые записи вместо JSON

---

#### 📁 Изменённые файлы

```
core/components/minishop3/
├── migrations/
│   └── 20251216120000_alter_order_logs_table.php  # Новая миграция
├── src/Model/
│   ├── msOrderLog.php                             # +константы ACTION_*
│   └── mysql/msOrderLog.php                       # +visible поле
├── src/Controllers/
│   ├── Cart/Cart.php                              # +логирование корзины
│   └── Order/
│       ├── Order.php                              # +логирование полей
│       ├── OrderLog.php                           # +shouldLog(), addEntry()
│       └── OrderStatus.php                        # (без изменений)
├── src/Processors/Order/
│   └── GetLog.php                                 # +форматирование entry
└── lexicon/
    ├── ru/setting.inc.php                         # +ms3_order_log_actions
    └── en/setting.inc.php                         # +ms3_order_log_actions

_build/elements/
└── settings.php                                   # +ms3_order_log_actions

vueManager/src/components/
└── OrderView.vue                                  # +formatLogEntry(), entry_formatted
```

---

#### 🔧 Особенности реализации

**Контроллер OrderLog:**
```php
// Проверка разрешения логирования
public function shouldLog(string $action): bool
{
    $actions = $this->modx->getOption('ms3_order_log_actions', null, 'status,products,field,address');
    if ($actions === '*') return true;
    return in_array($action, explode(',', $actions));
}

// Добавление записи с JSON данными
public function addEntry(int $orderId, string $action, array $data, bool $visible = true): bool
```

**Каскадное удаление:**
- Логи автоматически удаляются при удалении заказа
- Реализовано через xPDO composite relationship (`owner="local"`)

---

### [2025-12-15] ✨ Управление товарами заказа (CRUD)

**Контекст:** Реализован полный функционал управления товарами в заказе через Vue интерфейс: добавление, редактирование, удаление товаров с автоматическим пересчётом итогов заказа.

---

#### ✨ Добавлено

**1. Добавление товара в заказ**:
- Кнопка "Добавить товар" на вкладке "Товары"
- Диалог с AutoComplete поиском товаров (по названию, артикулу, ID)
- Превью товара в результатах поиска (картинка, название, артикул, цена)
- Форма с полями: количество, цена, вес
- Автоматический расчёт суммы
- Проверка статуса заказа (нельзя добавить в завершённый)

**2. Редактирование товара в заказе**:
- Диалог редактирования с полями: количество, цена, вес
- Редактор опций товара с двумя режимами:
  - **Table mode** - таблица ключ-значение
  - **JSON mode** - редактирование сырого JSON
- Для опций типа "Поле" (color, size) - выбор из доступных значений товара
- Автоматический пересчёт суммы при изменении количества/цены

**3. Удаление товара из заказа**:
- Подтверждение удаления через ConfirmDialog
- Защита от удаления последнего товара
- Автоматический пересчёт итогов заказа

**4. API endpoints**:
```
POST   /api/mgr/orders/{id}/products              - Добавить товар
PUT    /api/mgr/orders/{id}/products/{product_id} - Обновить товар
DELETE /api/mgr/orders/{id}/products/{product_id} - Удалить товар

GET    /api/mgr/references/products?query=...     - Поиск товаров (autocomplete)
GET    /api/mgr/references/product-option-fields  - Список полей-опций (color, size)
GET    /api/mgr/references/product-field-values?field=color&product_id=123 - Значения поля для товара
```

**5. Методы OrdersController.php**:
- `addProduct()` - добавление товара с валидацией и пересчётом
- `updateProduct()` - обновление полей товара
- `deleteProduct()` - удаление с проверками

**6. Методы ReferencesController.php**:
- `searchProducts()` - поиск товаров для autocomplete
- `getProductOptionFields()` - список полей-опций
- `getProductFieldValues()` - значения поля для конкретного товара

---

#### 📁 Изменённые файлы

```
core/components/minishop3/
├── config/routes/manager.php                     # +3 новых маршрута
├── src/Controllers/Api/Manager/
│   └── OrdersController.php                      # +addProduct, updateProduct, deleteProduct
├── src/Controllers/Api/
│   └── ReferencesController.php                  # +searchProducts, getProductOptionFields, getProductFieldValues
└── lexicon/
    ├── ru/vue.inc.php                           # +15 ключей
    └── en/vue.inc.php                           # +15 ключей

vueManager/src/components/
└── OrderView.vue                                 # Диалоги добавления/редактирования, AutoComplete
```

---

#### 🔧 Особенности реализации

**Редактор опций товара:**
- Переключение между Table/JSON режимами с синхронизацией данных
- Для сложных значений (массивы) - автоматический переход в JSON режим
- Выбор типа опции: "Поле товара" или "Произвольное значение"
- При выборе поля (color, size) - загрузка доступных значений из msProductData

**Защита от ошибок:**
- Нельзя добавить товар в заказ с финальным статусом
- Нельзя удалить последний товар из заказа
- Валидация JSON при редактировании опций

**Автоматический пересчёт:**
- При добавлении/редактировании/удалении товара пересчитываются:
  - `cart_cost` - сумма товаров
  - `weight` - общий вес
  - `cost` - итоговая стоимость (cart_cost + delivery_cost)

---

### [2025-12-15] ✨ Система конфигурируемых полей для моделей заказа

**Контекст:** Реализована универсальная система для настройки отображаемых полей на формах заказа. Администраторы могут управлять видимостью, порядком и группировкой полей для msOrder, msOrderAddress, msOrderProduct через UI в админке.

---

#### ✨ Добавлено

**1. Модели xPDO для конфигурации полей**:
- `msModelField` - конфигурация полей моделей (name, label, xtype, visible, rank, section_id, width, config)
- `msModelFieldSection` - секции для группировки полей (section_key, label, lexicon_key, sort_order)
- Поддержка 3 моделей: msOrder, msOrderAddress, msOrderProduct

**2. ComboConfigManager** (`src/Services/ComboConfigManager.php`):
- Управление конфигурациями combo/select полей
- Файловые конфиги в `config/combos/{model}.php`
- Кастомные конфиги в `custom/combos/{model}.php` (не перезаписываются при обновлении)
- Трёхуровневый приоритет: DB > custom file > default file
- Динамическая загрузка опций из xPDO моделей

**3. Конфигурации combo полей**:
```php
// config/combos/msOrder.php
return [
    'status_id' => [
        'source' => [
            'type' => 'model',
            'class' => 'MiniShop3\\Model\\msOrderStatus',
            'valueField' => 'id',
            'labelField' => 'name',
            'sort' => ['position' => 'ASC'],
        ],
    ],
    'delivery_id' => [...],
    'payment_id' => [...],
    'customer_id' => [...],
];
```

**4. Vue компонент ModelFieldsGrid.vue**:
- Выбор модели через TabView (msOrder, msOrderAddress, msOrderProduct)
- Drag-and-drop сортировка секций и полей
- Редактирование свойств полей (label, xtype, width, section, visible, required)
- CRUD операции для секций
- Поддержка JSON config для расширенных настроек combo полей

**5. API endpoints для управления полями** (`/api/mgr/model-fields`):
```
GET    /                          - Список полей (с фильтром по model)
GET    /{id}                      - Получить поле
POST   /                          - Создать поле
PUT    /{id}                      - Обновить поле
DELETE /{id}                      - Удалить поле
PUT    /ranks                     - Обновить порядок полей

GET    /models                    - Список доступных моделей
GET    /visible/{model}           - Видимые поля для формы

GET    /combo-options/{model}     - Все combo опции модели
GET    /combo-options/{model}/{field_name} - Опции конкретного поля

GET    /sections/{model}          - Секции модели
POST   /sections                  - Создать секцию
PUT    /sections/{id}             - Обновить секцию
DELETE /sections/{id}             - Удалить секцию
PUT    /sections/ranks            - Обновить порядок секций
```

**6. Обновлённый OrderView.vue**:
- Динамическая загрузка конфигурации полей через API
- Рендеринг полей по секциям (fieldset с legend)
- Поддержка combo полей с динамическими опциями (Dropdown компонент)
- 12-колоночная сетка для управления шириной полей

**7. Миграции**:
- `20251205120000_create_model_fields_table.php` - таблица ms3_model_fields
- `20251205120100_seed_model_fields.php` - стандартные поля msOrder
- `20251211120000_create_model_field_sections_table.php` - таблица ms3_model_field_sections
- `20251211120100_add_model_fields_columns.php` - дополнительные колонки
- `20251211120200_seed_model_field_sections.php` - секции и поля для всех моделей

---

#### 📁 Новые файлы

```
core/components/minishop3/
├── config/combos/
│   ├── msOrder.php                    # Combo конфиги для заказов
│   └── msOrderAddress.php             # Combo конфиги для адресов
├── migrations/
│   ├── 20251205120000_create_model_fields_table.php
│   ├── 20251205120100_seed_model_fields.php
│   ├── 20251211120000_create_model_field_sections_table.php
│   ├── 20251211120100_add_model_fields_columns.php
│   └── 20251211120200_seed_model_field_sections.php
├── src/
│   ├── Controllers/Api/Manager/
│   │   └── ModelFieldsController.php  # API контроллер (780 строк)
│   ├── Model/
│   │   ├── msModelField.php           # Модель поля
│   │   ├── msModelFieldSection.php    # Модель секции
│   │   └── mysql/
│   │       ├── msModelField.php       # MySQL mapping
│   │       └── msModelFieldSection.php
│   └── Services/
│       └── ComboConfigManager.php     # Менеджер combo конфигов

assets/components/minishop3/js/mgr/model-fields/
└── model-fields.wrapper.js            # ExtJS wrapper для Vue

vueManager/src/
├── components/
│   └── ModelFieldsGrid.vue            # Vue компонент (1200+ строк)
└── entries/
    └── model-fields.js                # Entry point
```

---

#### 🔧 Изменено

**1. OrderView.vue** - значительное обновление:
- Динамическая загрузка конфигурации полей
- Рендеринг по секциям
- Поддержка combo полей с опциями

**2. ProductDataConfig.vue**:
- Улучшения интерфейса редактирования полей

**3. Роуты менеджера** (`config/routes/manager.php`):
- Добавлена группа `/model-fields` с 15 endpoints

**4. Лексиконы** (`lexicon/ru/vue.inc.php`, `lexicon/en/vue.inc.php`):
- 100+ новых ключей для управления полями

**5. utilities.panel.js**:
- Добавлена вкладка "Поля моделей" (Model Fields)

---

#### 🗑️ Удалено

**ExtJS файлы заказов** (переход на Vue):
- `orders.form.js`, `orders.grid.js`, `orders.grid.logs.js`
- `orders.grid.products.js`, `orders.js`, `orders.panel.js`
- `orders.window.js`, `orders.window.product.js`

**ExtJS файлы клиентов** (переход на Vue):
- `customers.grid.js`, `customers.grid.addresses.js`
- `customers.js`, `customers.panel.js`
- `customers.window.js`, `customers.window.address.js`

---

#### 📝 Архитектурные решения

**Трёхуровневая система конфигурации:**
1. **Default файлы** (`config/combos/`) - поставляются с компонентом
2. **Custom файлы** (`custom/combos/`) - пользовательские, не перезаписываются
3. **База данных** (`ms3_model_fields.config`) - наивысший приоритет, редактируется через UI

**Универсальность:**
- Единый механизм для всех моделей (msOrder, msOrderAddress, msOrderProduct)
- Легко расширяется на другие модели
- Combo конфиги отделены от конфигурации полей

---

### [2025-12-04] ✨ Vue страница заказов с конфигурируемыми фильтрами

**Контекст:** Модернизация страницы управления заказами в админке. Переход с ExtJS на Vue 3 + PrimeVue. Реализована система конфигурируемых фильтров через файловые конфиги.

---

#### ✨ Добавлено

**1. Vue страница списка заказов (OrdersGrid.vue)**:
- DataTable с пагинацией, сортировкой, выбором строк
- Динамические фильтры на основе конфигурации
- Цветовые бейджи статусов
- Контекстное меню (редактирование, удаление)
- Статистика заказов (за месяц, за неделю)

**2. Vue страница редактирования заказа (OrderView.vue)**:
- Просмотр и редактирование данных заказа
- Получение order_id из URL параметров

**3. Система конфигурируемых фильтров (FilterConfigManager)**:
- Файловые конфиги в `config/filters/{grid}.php`
- Пользовательские конфиги в `custom/filters/{grid}.php` (не перезаписываются при обновлении)
- Поддержка типов фильтров: text, select, datepicker, daterange
- Источники данных для select: model (xPDO), static, api
- Автоматический перевод лексиконов для названий статусов

**4. API endpoints для заказов**:
```
GET    /api/mgr/orders              - Список заказов с фильтрами и пагинацией
GET    /api/mgr/orders/{id}         - Получить заказ
POST   /api/mgr/orders              - Создать заказ
PUT    /api/mgr/orders/{id}         - Обновить заказ
DELETE /api/mgr/orders/{id}         - Удалить заказ
GET    /api/mgr/orders/filters      - Конфигурация фильтров
GET    /api/mgr/orders/grid-config  - Конфигурация колонок грида
```

**5. OrdersController** (`src/Controllers/Api/Manager/OrdersController.php`):
- CRUD операции для заказов
- Поддержка фильтров: status_id, delivery_id, payment_id, createdon_from, createdon_to, query
- Статистика заказов (getOrdersStats)
- Интеграция с FilterConfigManager

**6. Конфигурация фильтров заказов** (`config/filters/orders.php`):
```php
return [
    'query' => [
        'type' => 'text',
        'fields' => ['num', 'email', 'phone'],
        'operator' => 'like',
    ],
    'status_id' => [
        'type' => 'select',
        'source' => [
            'type' => 'model',
            'class' => 'MiniShop3\\Model\\msOrderStatus',
            'where' => ['active' => true],
        ],
    ],
    // delivery_id, payment_id, createdon (daterange)
];
```

---

#### 📁 Новые файлы

```
core/components/minishop3/
├── config/filters/
│   └── orders.php                    # Конфигурация фильтров заказов
├── custom/filters/
│   └── .gitkeep                      # Папка для пользовательских конфигов
├── controllers/mgr/
│   └── order.class.php               # Контроллер страницы редактирования заказа
├── src/
│   ├── Controllers/Api/Manager/
│   │   └── OrdersController.php      # API контроллер заказов
│   └── Services/
│       └── FilterConfigManager.php   # Менеджер конфигурации фильтров

assets/components/minishop3/js/mgr/orders/
├── orders.wrapper.js                 # ExtJS wrapper для Vue списка заказов
└── order.wrapper.js                  # ExtJS wrapper для Vue редактирования заказа

vueManager/src/
├── components/
│   ├── OrdersGrid.vue                # Vue компонент списка заказов
│   └── OrderView.vue                 # Vue компонент редактирования заказа
└── entries/
    ├── orders.js                     # Entry point для списка заказов
    └── order.js                      # Entry point для редактирования заказа
```

---

#### 🔧 Изменено

**1. Роуты менеджера** (`config/routes/manager.php`):
- Добавлены маршруты для API заказов и фильтров

**2. Контроллер страницы заказов** (`controllers/mgr/orders.class.php`):
- Подключение Vue assets вместо ExtJS

**3. Vite конфигурация** (`vueManager/vite.config.js`):
- Добавлены entry points: orders, order

**4. Лексиконы** (`lexicon/ru/vue.inc.php`, `lexicon/en/vue.inc.php`):
- Добавлены ключи для страницы заказов и фильтров

---

#### 📝 Архитектурные решения

**Конфигурируемые фильтры:**
- Выбран файловый подход (без БД) для простоты и производительности
- Паттерн "default + custom override" для защиты от перезаписи при обновлении
- Поддержка разных источников данных (model, static, api)

**xPDO Best Practice:**
- Использование `getIterator()` вместо `getCollection()` для экономии памяти

---

### [2025-12-02] ✨ Notification Center - Централизованное управление уведомлениями

**Контекст:** Создана отдельная страница админки для управления настройками уведомлений. Функционал email-уведомлений вынесен из статусов заказов в централизованный Notification Center.

---

#### ✨ Добавлено

**1. Страница Notification Center в админке**:
- Vue 3 + PrimeVue интерфейс (как страница Клиенты)
- CRUD операции для настроек уведомлений
- Фильтры по статусу, каналу, типу получателя
- Автоматический расчёт контрастного цвета текста для бейджей статусов

**2. API endpoints для управления уведомлениями**:
```
GET    /api/mgr/notifications           - Список настроек
GET    /api/mgr/notifications/{id}      - Получить настройку
POST   /api/mgr/notifications           - Создать настройку
PUT    /api/mgr/notifications/{id}      - Обновить настройку
DELETE /api/mgr/notifications/{id}      - Удалить настройку
GET    /api/mgr/notifications/references - Справочники (статусы, каналы, события)
```

**3. NotificationsController** (`src/Controllers/Api/Manager/NotificationsController.php`):
- CRUD операции для msNotificationConfig
- Метод `getReferences()` с автопереводом статусов через лексиконы
- Проверка уникальности комбинации (event + status_id + recipient_type + channel)

**4. Vue компоненты**:
- `NotificationsGrid.vue` - основной грид с фильтрами и модальным окном редактирования
- `notifications.js` - entry point для Vue приложения
- `notifications.wrapper.js` - ExtJS wrapper для монтирования Vue

**5. Лексиконы (RU/EN)**:
```php
// notifications.inc.php
$_lang['ms3_notifications'] = 'Уведомления';
$_lang['ms3_notifications_title'] = 'Центр уведомлений';
$_lang['ms3_notification_event'] = 'Событие';
$_lang['ms3_notification_event_status_changed'] = 'Изменение статуса заказа';
$_lang['ms3_notification_event_order_created'] = 'Создание заказа';
$_lang['ms3_notification_recipient_customer'] = 'Клиент';
$_lang['ms3_notification_recipient_manager'] = 'Менеджер';
// и другие...
```

**6. Функция расчёта контрастного цвета текста**:
```javascript
// Скопирована из ms3.utils.renderBadge
function getContrastTextColor(hexColor) {
  // HEX → RGB → HSL (яркость L)
  // L > 50% → чёрный текст, иначе → белый текст
}
```

---

#### 🔧 Изменено

**1. Удалены устаревшие поля email из msOrderStatus**:
- Убраны: `email_user`, `email_manager`, `subject_user`, `subject_manager`, `body_user`, `body_manager`
- Из модели (PHPDoc, fields, fieldMeta)
- Из XML схемы
- Из ExtJS виджетов (window.js, grid.js)
- Из процессоров (Create.php, Update.php, GetList.php)

**2. Миграция для удаления устаревших колонок**:
```php
// 20251202000736_remove_email_fields_from_order_status.php
$table->removeColumn('email_user');
$table->removeColumn('email_manager');
$table->removeColumn('subject_user');
$table->removeColumn('subject_manager');
$table->removeColumn('body_user');
$table->removeColumn('body_manager');
```

---

#### 📁 Новые файлы

```
core/components/minishop3/
├── controllers/mgr/notifications.class.php
├── src/Controllers/Api/Manager/NotificationsController.php
├── lexicon/ru/notifications.inc.php
├── lexicon/en/notifications.inc.php
├── migrations/20251202000736_remove_email_fields_from_order_status.php

assets/components/minishop3/js/mgr/
├── notifications/notifications.wrapper.js

vueManager/src/
├── entries/notifications.js
├── components/NotificationsGrid.vue
```

---

#### 🔌 Архитектура системы каналов уведомлений

Система поддерживает расширяемые каналы уведомлений:

**Встроенный канал:** `EmailChannel` (регистрируется автоматически)

**Готовые каналы (требуют регистрации через плагин)**:
- `TelegramChannel` - требует системную настройку `ms3_telegram_bot_token`
- `SmsChannel` - для интеграции с SMS-провайдерами

**Регистрация кастомного канала через плагин**:
```php
// Плагин на событие msOnRegisterNotificationChannels
$manager = $scriptProperties['manager'];
$manager->registerChannel(new TelegramChannel($modx));
```

**Интерфейс канала** (`ChannelInterface`):
```php
interface ChannelInterface {
    public function send(Notification $notification, array $recipient, msOrder $order): bool;
    public function getName(): string;
    public function isAvailable(): bool;
    public function getRequirements(): array;
}
```

---

## Ноябрь 2025

### [2025-11-30] ✨ Личный кабинет клиента: Адреса и Заказы + Мультиязычность статусов

**Контекст:** Реализован полноценный фронтенд личного кабинета клиента с управлением адресами и просмотром истории заказов. Добавлена мультиязычная поддержка статусов заказов через лексиконы.

---

#### ✨ Добавлено

**1. Страница адресов клиента (`/cabinet/addresses`)**:
- Отображение списка сохранённых адресов клиента
- Установка адреса по умолчанию (звёздочка)
- Удаление адреса с подтверждением
- SVG иконки в inline формате (не sprite)
- Адаптивный Bootstrap 5 layout

**2. Страница заказов клиента (`/cabinet/orders`)**:
- Список всех заказов (исключая черновики)
- Фильтрация по статусу заказа
- Пагинация для большого количества заказов
- Детальный просмотр заказа с товарами
- Форматирование цен и дат

**3. Поле `is_default` для адресов**:
```php
// msCustomerAddress model
'is_default' => [
    'dbtype' => 'tinyint',
    'precision' => '1',
    'phptype' => 'integer',
    'null' => false,
    'default' => 0,
]
```

**4. Web API для управления адресами**:
- `PUT /api/v1/customer/addresses/{id}/set-default` - Установить адрес по умолчанию
- `DELETE /api/v1/customer/addresses/{id}` - Удалить адрес

**5. JS модуль для работы с адресами**:
```javascript
// assets/components/minishop3/js/web/modules/customer-addresses.js
class CustomerAddresses {
    constructor(config = {})
    handleSetDefault(e)  // PUT запрос на set-default
    handleDelete(e)      // DELETE запрос с подтверждением
}
```

**6. Мультиязычность статусов заказов**:
- Статусы хранятся как лексиконные ключи (`ms3_order_status_new`, `ms3_order_status_paid` и т.д.)
- Автоматический перевод при отображении через `translateStatusName()`
- Поддержка пользовательских статусов (без префикса `ms3_order_status_`)

**7. Новые лексиконы (RU/EN)**:
```php
// customer.inc.php
$_lang['ms3_customer_addresses_title'] = 'Мои адреса';
$_lang['ms3_customer_addresses_empty'] = 'У вас пока нет сохранённых адресов';
$_lang['ms3_customer_address_set_default'] = 'Сделать основным';
$_lang['ms3_customer_address_delete_confirm'] = 'Удалить этот адрес?';

// default.inc.php
$_lang['ms3_frontend_article'] = 'Артикул';
$_lang['ms3_frontend_cart_total'] = 'Сумма товаров';
$_lang['ms3_frontend_delivery_address'] = 'Адрес доставки';
// и другие...
```

---

#### 🔧 Изменено

**1. TokenMiddleware** - Добавлена проверка сессии:
```php
// Проверяем сессию перед токеном
if (!empty($_SESSION['ms3']['customer_id'])) {
    $customer = $this->modx->getObject(msCustomer::class, $_SESSION['ms3']['customer_id']);
    if ($customer) {
        return null; // Авторизован через сессию
    }
}
```

**2. OrdersPageService** - Добавлен метод `translateStatusName()`:
```php
protected function translateStatusName(string $name): string
{
    if (str_starts_with($name, 'ms3_order_status_')) {
        $this->modx->lexicon->load('minishop3:manager');
        $translated = $this->modx->lexicon($name);
        if ($translated !== $name) {
            return $translated;
        }
    }
    return $name;
}
```

**3. Миграция seed_order_statuses** - Лексиконные ключи вместо hardcoded:
```php
$data = [
    ['id' => 1, 'name' => 'ms3_order_status_draft', ...],
    ['id' => 2, 'name' => 'ms3_order_status_new', ...],
    ['id' => 3, 'name' => 'ms3_order_status_paid', ...],
    ['id' => 4, 'name' => 'ms3_order_status_sent', ...],
    ['id' => 5, 'name' => 'ms3_order_status_cancelled', ...],
];
```

**4. Процессоры статусов** - Добавлен перевод:
- `Order/GetList.php` - перевод `status_name` в списке заказов
- `Settings/Status/GetList.php` - перевод в настройках админки
- `Settings/Status/Get.php` - перевод при редактировании

---

#### 🐛 Исправлено

**1. Ошибка Fenom с `{'Content-Type':`**:
- **Проблема**: Fenom интерпретировал `{'Content-Type':` как переменную
- **Решение**: JS перенесён в отдельный файл `customer-addresses.js`

**2. Ошибка "Route parameter is required"**:
- **Проблема**: JS использовал старый формат `ms3_action`
- **Решение**: Исправлено на `route=/api/v1/...`

**3. Ошибка `ms3_err_token`**:
- **Проблема**: TokenMiddleware не проверял сессию
- **Решение**: Добавлена проверка `$_SESSION['ms3']['customer_id']`

**4. Множественный default адрес**:
- **Проблема**: `$this->modx->exec()` не работал корректно
- **Решение**: Заменено на `$this->modx->prepare()` + `execute()`

**5. Миграция system_settings**:
- **Проблема**: Phinx не поддерживает prepared statement placeholders
- **Решение**: Использование прямых SQL строк

---

#### 📁 Новые файлы

```
assets/components/minishop3/js/web/modules/customer-addresses.js
core/components/minishop3/src/Controllers/Api/Manager/CustomerAddressesController.php
vueManager/src/actionRegistry.js
vueManager/src/components/ActionsColumn.vue
vueManager/src/components/ActionsEditor.vue
vueManager/src/composables/useActions.js
```

---

#### 📁 Изменённые файлы

```
core/components/minishop3/config/routes/web.php
core/components/minishop3/elements/chunks/ms3_customer_addresses.tpl
core/components/minishop3/elements/chunks/ms3_customer_address_row.tpl
core/components/minishop3/elements/chunks/ms3_customer_orders.tpl
core/components/minishop3/lexicon/*/customer.inc.php
core/components/minishop3/lexicon/*/default.inc.php
core/components/minishop3/migrations/20251023000000_seed_order_statuses.php
core/components/minishop3/src/Controllers/Api/Web/CustomerAddressController.php
core/components/minishop3/src/Middleware/TokenMiddleware.php
core/components/minishop3/src/Model/msCustomerAddress.php
core/components/minishop3/src/Model/mysql/msCustomerAddress.php
core/components/minishop3/src/Processors/Order/GetList.php
core/components/minishop3/src/Processors/Settings/Status/Get.php
core/components/minishop3/src/Processors/Settings/Status/GetList.php
core/components/minishop3/src/Services/Customer/OrdersPageService.php
vueManager/src/components/CustomersGrid.vue
vueManager/src/components/GridFieldsConfig.vue
```

---

### [2025-11-27] ✨ Новая функция: Полный CRUD для полей гридов с поддержкой 4 типов полей

**Контекст:** Реализован мощный конфигуратор таблиц (гридов) в админке с возможностью добавления, редактирования и удаления полей. Поддерживаются 4 типа полей: Model (прямые поля из БД), Template (шаблоны с подстановкой), Relation (JOIN запросы с агрегацией), Computed (PHP классы с кастомной логикой). Это позволяет администраторам гибко настраивать отображение данных в таблицах клиентов, заказов, товаров без изменения кода.

---

#### ✨ Добавлено

**1. Четыре типа полей грида:**

**Model Field** - Прямое поле из таблицы БД
```javascript
{
  field_name: 'email',
  type: 'model',
  label: 'Email',
  visible: true,
  sortable: true,
  filterable: true
}
```

**Template Field** - Шаблон с подстановкой полей (фильтрация отключена для template полей)
```javascript
{
  field_name: 'full_name',
  type: 'template',
  config: {
    template: '{first_name} {last_name}'
  },
  filterable: false  // Автоматически disabled в UI
}
```

**Relation Field** - JOIN запросы с агрегацией (COUNT, SUM, AVG, MIN, MAX)
- Поддержка table name (`modx_ms3_orders`) и model class (`msOrder` или `MiniShop3\Model\msOrder`)
- Batch processing - один JOIN для всех строк (избегает N+1 query problem)
```javascript
{
  field_name: 'orders_count',
  type: 'relation',
  config: {
    relation: {
      table: 'modx_ms3_orders',  // или 'msOrder'
      foreignKey: 'customer_id',
      displayField: 'id',
      aggregation: 'COUNT'  // COUNT, SUM, AVG, MIN, MAX
    }
  }
}
```

**Computed Field** - PHP класс с кастомной логикой
```javascript
{
  field_name: 'customer_tier',
  type: 'computed',
  config: {
    computed: {
      className: 'MiniShop3\\Computed\\CustomerTier'
    }
  }
}
```

**2. CRUD операции для полей:**
- ✅ **Create** - Добавление поля через Dialog с валидацией
- ✅ **Read** - Загрузка конфигурации грида
- ✅ **Update** - Редактирование поля (имя поля read-only)
- ✅ **Delete** - Удаление с подтверждением (системные поля защищены)

**3. Vue UI компоненты:**
- ✅ Кнопка "Добавить поле" с динамическим Dialog
- ✅ Кнопка "Редактировать" (иконка карандаша) в каждой строке
- ✅ Динамические формы в зависимости от типа поля
- ✅ Dropdown для выбора агрегации (COUNT, SUM, AVG, MIN, MAX)
- ✅ Автоматическое отключение фильтрации для Template полей
- ✅ Валидация на клиенте и сервере

**4. Computed Field Interface:**
```php
<?php
namespace MiniShop3\Interfaces;

interface ComputedFieldInterface
{
    /**
     * Вычислить значение для одной строки грида
     * @param array $row Данные строки грида
     * @return mixed Вычисленное значение
     */
    public function compute(array $row): mixed;
}
```

**5. Пример Computed класса - CustomerTier:**
```php
<?php
namespace MiniShop3\Computed;

use MiniShop3\Interfaces\ComputedFieldInterface;

class CustomerTier implements ComputedFieldInterface
{
    public function compute(array $row): string
    {
        $totalSpent = (float)($row['total_spent'] ?? 0);

        if ($totalSpent >= 100000) {
            return 'VIP';
        } elseif ($totalSpent >= 10000) {
            return 'Regular';
        } else {
            return 'New';
        }
    }
}
```

---

#### 🏗️ Архитектура

**Backend (PHP):**

**GridConfigService** - Бизнес-логика управления полями
- `addField(string $gridKey, array $data): array` - Добавить поле с валидацией по типу
- `updateField(string $gridKey, string $fieldName, array $data): array` - Обновить поле
- `deleteField(string $gridKey, string $fieldName): array` - Удалить поле (защита системных)
- `validateTemplateConfig(array $config): array` - Валидация template полей
- `validateRelationConfig(array $config): array` - Валидация relation полей (авто-резолв table name)
- `validateComputedConfig(array $config): array` - Валидация computed полей (проверка interface)

**CustomersController** - Выполнение relation и computed полей
- `extractRelationFields(array $gridFields): array` - Извлечь relation поля из конфига
- `extractComputedFields(array $gridFields): array` - Извлечь computed поля из конфига
- `fetchRelationData(array $customerIds, array $relationFields): array` - Batch JOIN для всех клиентов
- `computeField(array $row, array $config): mixed` - Вычислить значение computed поля

**Batch Processing для Relation полей:**
```php
// Один JOIN запрос для ВСЕХ клиентов (O(1) вместо O(n))
$sql = "
    SELECT
        {$customersTable}.id as customer_id,
        COUNT({$relationTable}.{$displayField}) as field_value
    FROM {$customersTable}
    LEFT JOIN {$relationTable} ON {$relationTable}.{$foreignKey} = {$customersTable}.id
    WHERE {$customersTable}.id IN (" . implode(',', $customerIds) . ")
    GROUP BY {$customersTable}.id
";
```

**Frontend (Vue 3):**

**GridFieldsConfig.vue** - Главный компонент
- State: `newField`, `editingField`, `showAddDialog`, `showEditDialog`
- Methods:
  - `openAddDialog()` / `closeAddDialog()` / `addField()`
  - `openEditDialog(field, index)` / `closeEditDialog()` / `saveEdit()`
  - `deleteField(field, index)` - с ConfirmDialog
- Динамические формы на основе `field.type`
- Валидация на клиенте

---

#### 🌐 API Endpoints

**POST** `/api/mgr/grid-config/{grid_key}/field` - Добавить поле
```json
{
  "field_name": "orders_count",
  "label": "Количество заказов",
  "type": "relation",
  "visible": true,
  "sortable": false,
  "filterable": false,
  "frozen": false,
  "width": "150px",
  "config": {
    "relation": {
      "table": "modx_ms3_orders",
      "foreignKey": "customer_id",
      "displayField": "id",
      "aggregation": "COUNT"
    }
  }
}
```

**PUT** `/api/mgr/grid-config/{grid_key}/field/{field_name}` - Обновить поле
- Имя поля нельзя изменить (read-only в UI)
- Обновляются: label, type, visible, sortable, filterable, frozen, width, config

**DELETE** `/api/mgr/grid-config/{grid_key}/{field_name}` - Удалить поле
- Системные поля (`is_system = true`) защищены от удаления

---

#### 🔧 Технические детали

**1. Защита от N+1 Query Problem:**
- Relation поля выполняются batch запросом с `IN (customer_ids)`
- GROUP BY для агрегации
- Один JOIN для всех строк таблицы

**2. Универсальная поддержка Table Name / Model Class:**
```php
// Автоматическое определение типа
$isModel = strpos($tableOrModel, '\\') !== false || strpos($tableOrModel, '::') !== false;

if ($isModel) {
    // Резолвим имя таблицы из модели
    $tableName = $this->modx->getTableName($tableOrModel);
    $config['relation']['resolvedTableName'] = $tableName;
} else {
    // Используем прямое имя таблицы
    $config['relation']['resolvedTableName'] = $tableOrModel;
}
```

**3. Interface-based валидация:**
```php
// Проверка реализации ComputedFieldInterface
$interfaces = class_implements($className);
if (!isset($interfaces['MiniShop3\\Interfaces\\ComputedFieldInterface'])) {
    return ['success' => false, 'message' => 'Class must implement ComputedFieldInterface'];
}
```

**4. Инициализация с нулями для Relation полей:**
```php
// Всегда показываем 0, а не пустое значение
foreach ($customerIds as $customerId) {
    foreach ($relationFields as $fieldName => $config) {
        $result[$customerId][$fieldName] = 0;
    }
}
```

**5. Двойное назначение displayField:**
- **Без агрегации**: поле для отображения (например, `name` → показывает имя поставщика)
- **С агрегацией**: поле для применения функции (например, `id` → `COUNT(orders.id)`)

---

#### 📦 Затронутые файлы

**Backend (PHP):**
- `core/components/minishop3/src/Interfaces/ComputedFieldInterface.php` *(новый)* - Интерфейс для computed полей
- `core/components/minishop3/src/Computed/CustomerTier.php` *(новый)* - Пример computed класса
- `core/components/minishop3/src/Services/GridConfigService.php` - Добавлены методы addField(), updateField() + валидация
- `core/components/minishop3/src/Controllers/Api/Manager/GridConfigController.php` - Добавлены addField(), updateField()
- `core/components/minishop3/src/Controllers/Api/Manager/CustomersController.php` - Добавлена обработка relation/computed полей
- `core/components/minishop3/config/routes/manager.php` - Роуты POST/PUT для полей (строки 394-415)

**Frontend (Vue 3):**
- `vueManager/src/components/GridFieldsConfig.vue` - Добавлены Add/Edit Dialog, методы CRUD

**Лексиконы (русский + английский):**
- `core/components/minishop3/lexicon/ru/vue.inc.php` - 35+ новых ключей
- `core/components/minishop3/lexicon/en/vue.inc.php` - 35+ новых ключей
  - Add/Edit dialog strings
  - Field type labels (Model, Template, Relation, Computed)
  - Relation config labels (table, foreignKey, displayField, aggregation)
  - Aggregation options (COUNT, SUM, AVG, MIN, MAX)
  - Success/error messages

---

#### 💡 Примеры использования

**Добавить поле "Полное имя" (Template):**
1. Открыть Grid Fields Configuration
2. Нажать "Добавить поле"
3. Имя поля: `full_name`
4. Тип: Template
5. Шаблон: `{first_name} {last_name}`
6. Создать

**Добавить поле "Количество заказов" (Relation):**
1. Добавить поле
2. Имя: `orders_count`
3. Тип: Relation
4. Таблица: `modx_ms3_orders` (или `msOrder`)
5. Внешний ключ: `customer_id`
6. Поле для отображения: `id`
7. Агрегация: COUNT (количество)
8. Создать

**Добавить поле "Категория клиента" (Computed):**
1. Добавить поле
2. Имя: `customer_tier`
3. Тип: Computed
4. Класс: `MiniShop3\Computed\CustomerTier`
5. Создать

**Редактировать существующее поле:**
1. Нажать иконку карандаша рядом с полем
2. Изменить настройки (label, тип, конфиг)
3. Имя поля *нельзя изменить* после создания
4. Сохранить

---

### [2025-11-24] 🗑️ Очистка: Удалены устаревшие чанки личного кабинета

**Контекст:** После рефакторинга layout личного кабинета остались устаревшие чанки, использующие старый подход с `?service=` в URL. Так как продукт разрабатывается с нуля, backward compatibility не требуется - устаревшие чанки удалены.

#### 🗑️ Удалено

**Удалённые файлы:**
- `ms3_customer_layout.tpl` - старый layout с встроенным sidebar
- `ms3_customer_account_simple.tpl` - упрощенная версия старого подхода

**Убрано из регистрации (_build/elements/chunks.php):**
```php
- 'tpl.msCustomer.layout' => 'ms3_customer_layout',
- 'msCustomer.account.simple' => 'ms3_customer_account_simple',
```

#### ✅ Актуальные чанки

**Используйте только эти чанки:**
- `tpl.msCustomer.base` → `ms3_customer_base.tpl` - базовый layout (sidebar + content)
- `tpl.msCustomer.sidebar` → `ms3_customer_sidebar.tpl` - переиспользуемый sidebar для отдельных страниц

**⚠️ Требуется:** Пересобрать компонент через `php _build/build.php` для применения изменений

---

### [2025-11-24] 🔧 Оптимизация: Убрана дублирующая проверка авторизации в сниппете

**Контекст:** В сниппете `ms3_customer.php` была обнаружена дублирующая проверка авторизации - один раз на уровне сниппета (строка 23) и второй раз внутри сервиса через `checkAuth()` (строка 59). Убрана ранняя проверка, оставлена только проверка в сервисе.

#### ♻️ Изменения

**Удалено из `ms3_customer.php`:**
```php
// 1. ПРОВЕРКА АВТОРИЗАЦИИ (первым делом!)
if (empty($_SESSION['ms3']['customer_id'])) {
    // Клиент НЕ авторизован - показать форму входа/регистрации
    $unauthorizedTpl = $modx->getOption('unauthorizedTpl', $scriptProperties, 'tpl.msCustomer.unauthorized');

    $chunk = $pdoFetch->getChunk($unauthorizedTpl, [...]);
    return is_string($chunk) ? $chunk : '';
}
```

**Почему убрали:**
- ✅ Метод `checkAuth()` в `CustomerPageService` делает больше: проверяет сессию + загружает объект клиента
- ✅ Централизует логику авторизации в одном месте (в сервисном классе)
- ✅ Корректно обрабатывает оба режима: `return=data` и `return=tpl`
- ✅ Использует метод `renderUnauthorized()` для единообразного отображения

**Результат:**
- Упрощён сниппет (убрано 16 строк дублирующего кода)
- Одна точка проверки авторизации вместо двух
- Более чистая архитектура

**Затронутые файлы:**
- `core/components/minishop3/elements/snippets/ms3_customer.php` - убрана ранняя проверка авторизации

---

### [2025-11-23] ♻️ Рефакторинг: Модульная структура layout личного кабинета

**Контекст:** Упрощена архитектура личного кабинета клиента. Layout теперь реализуется на уровне MODX шаблонов, а не в сниппете. Сниппет просто возвращает контент нужного раздела (профиль, адреса, заказы). Базовый layout и sidebar вынесены в переиспользуемые чанки.

---

#### 🎯 Новая архитектура

**Было (старая схема):**
```
Сниппет msCustomer → Рендерит контент → Оборачивает в layout чанк (если withLayout=1) → Возвращает HTML
```

**Стало (новая схема):**
```
MODX Шаблон → Включает sidebar чанк + сниппет msCustomer → Сниппет возвращает только контент раздела
```

**Преимущества:**
- ✅ Проще кастомизировать layout (редактируешь MODX шаблон, а не параметры сниппета)
- ✅ Больше гибкости (можно разместить sidebar справа, сверху, или вообще убрать)
- ✅ Меньше параметров в сниппете (убраны `withLayout` и `layoutTpl`)
- ✅ Понятнее разделение ответственности (шаблон = layout, сниппет = контент)

---

#### 📦 Созданные чанки

**1. Sidebar (боковая панель):**
- **Файл:** `core/components/minishop3/elements/chunks/ms3_customer_sidebar.tpl`
- **Назначение:** Навигация, информация о клиенте, аватар, статус верификации
- **Использование:** Включается в MODX шаблон через `[[$ms3_customer_sidebar]]`
- **Самодостаточный:** Получает данные клиента из сессии, определяет активный раздел по ID страницы

**2. Базовый layout (опциональный):**
- **Файл:** `core/components/minishop3/elements/chunks/ms3_customer_base.tpl`
- **Назначение:** Пример готового layout (sidebar + content в Bootstrap grid)
- **Использование:** Можно вызвать как `[[$ms3_customer_base]]`, передав `$content`

**3. Упрощённый сниппет:**
- Убраны параметры `withLayout` и `layoutTpl`
- Сниппет просто рендерит контент раздела и возвращает HTML
- Layout делается на уровне MODX шаблона

---

#### 📝 Структура файлов

```
core/components/minishop3/elements/chunks/
├── ms3_customer_base.tpl              ← Базовый layout (sidebar + content)
├── ms3_customer_sidebar.tpl           ← Переиспользуемая боковая панель
├── ms3_customer_profile.tpl           ← Контент профиля (без layout)
├── ms3_customer_addresses.tpl         ← Контент адресов (без layout)
├── ms3_customer_orders.tpl            ← Контент заказов (без layout)
└── ms3_customer_unauthorized.tpl      ← Форма входа/регистрации
```

---

#### 🏗️ Использование

**1. Создать MODX шаблон для личного кабинета:**

`customer.tpl` (наследует `base.tpl`):
```html
<!-- Наследование базового шаблона -->
{extends 'file:templates/base.tpl'}

{block 'content'}
<div class="container my-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 col-md-4 mb-4">
            [[$ms3_customer_sidebar]]
        </div>

        <!-- Контент раздела -->
        <div class="col-lg-9 col-md-8">
            <div class="customer-content">
                [[!msCustomer?
                    &service=`profile`
                ]]
            </div>
        </div>
    </div>
</div>
{/block}
```

**2. Создать отдельные страницы для каждого раздела:**

- **Страница "Профиль"** (ID = 10, шаблон: `customer.tpl`)
  ```modx
  [[!msCustomer?
      &service=`profile`
  ]]
  ```

- **Страница "Адреса"** (ID = 11, шаблон: `customer.tpl`)
  ```modx
  [[!msCustomer?
      &service=`addresses`
  ]]
  ```

- **Страница "Заказы"** (ID = 12, шаблон: `customer.tpl`)
  ```modx
  [[!msCustomer?
      &service=`orders`
  ]]
  ```

**3. Настроить системные настройки:**
```
ms3_customer_profile_page_id = 10
ms3_customer_addresses_page_id = 11
ms3_customer_orders_page_id = 12
```

**Альтернатива: Единая страница с переключением через параметр**

```modx
[[!msCustomer?
    &service=`[[+service:default=`profile`]]`
]]
```
- URL: `/account/` → Профиль
- URL: `/account/?service=addresses` → Адреса
- URL: `/account/?service=orders` → Заказы

---

#### 🎨 Особенности sidebar

**Информация о клиенте:**
- Аватар с инициалами (градиентный фон)
- Имя и фамилия (или "Гость" если не указаны)
- Email клиента
- Статус верификации email (зелёный бейдж если подтверждён)

**Навигация:**
- Профиль → `ms3_customer_profile_page_id`
- Адреса → `ms3_customer_addresses_page_id`
- Заказы → `ms3_customer_orders_page_id`
- Выход (с подтверждением)

**SVG иконки:**
- Встроенные inline SVG (не требуют внешних файлов)
- Адаптивные размеры под мобильные устройства

**Автоподсветка:**
- Активный раздел выделяется через `{if $current_section == 'profile'}active{/if}`
- Цвет активной ссылки: градиент `#667eea`

---

#### 📦 Новые системные настройки

**Файлы:**
- `core/components/minishop3/lexicon/ru/setting.inc.php`
- `core/components/minishop3/lexicon/en/setting.inc.php`

**Добавлены:**
```php
$_lang['setting_ms3_customer_profile_page_id'] = 'ID страницы профиля клиента';
$_lang['setting_ms3_customer_addresses_page_id'] = 'ID страницы адресов клиента';
$_lang['setting_ms3_customer_orders_page_id'] = 'ID страницы заказов клиента';
```

---

#### 🔤 Новые лексиконы

**Файлы:**
- `core/components/minishop3/lexicon/ru/customer.inc.php`
- `core/components/minishop3/lexicon/en/customer.inc.php`

**Добавлены:**
```php
$_lang['ms3_customer_guest'] = 'Гость';
$_lang['ms3_customer_logout'] = 'Выход';
$_lang['ms3_customer_logout_confirm'] = 'Вы действительно хотите выйти?';
```

---

#### 💡 Примеры кастомизации

**Пример 1: Кастомный sidebar**

Создайте свой чанк `my_custom_sidebar.tpl`:
```fenom
<nav class="my-sidebar">
    <div class="user-info">
        <h4>{$customer.first_name} {$customer.last_name}</h4>
        <p>{$customer.email}</p>
    </div>
    <ul class="nav-menu">
        <li><a href="{'ms3_customer_profile_page_id' | option | url}">Профиль</a></li>
        <li><a href="{'ms3_customer_orders_page_id' | option | url}">Заказы</a></li>
    </ul>
</nav>
```

Используйте в шаблоне:
```html
<div class="row">
    <div class="col-md-3">
        [[$my_custom_sidebar]]
    </div>
    <div class="col-md-9">
        [[!msCustomer? &service=`profile`]]
    </div>
</div>
```

**Пример 2: Layout без sidebar**

Просто не включайте sidebar в шаблон:
```html
{block 'content'}
<div class="container">
    [[!msCustomer? &service=`profile`]]
</div>
{/block}
```

**Пример 3: Sidebar справа**

```html
<div class="row">
    <div class="col-md-9">
        [[!msCustomer? &service=`profile`]]
    </div>
    <div class="col-md-3">
        [[$ms3_customer_sidebar]]
    </div>
</div>
```

---

#### ⚙️ Технические детали

**Как работает sidebar чанк:**

`ms3_customer_sidebar.tpl` - самодостаточный чанк, не требует передачи параметров.

**Автоматическое получение данных:**
```fenom
{* 1. Получить ID клиента из сессии *}
{set $customer_id = $_SESSION['ms3']['customer_id'] ?: 0}

{* 2. Загрузить объект клиента *}
{set $customer = $_modx->getObject('MiniShop3\Model\msCustomer', $customer_id)}

{* 3. Определить активный раздел по ID текущей страницы *}
{set $profile_id = 'ms3_customer_profile_page_id' | option}
{set $current_id = $_modx->resource.id}
{if $current_id == $profile_id}
    {set $current_section = 'profile'}
{/if}
```

**Использование в шаблоне:**
```modx
<!-- Никаких параметров передавать не нужно -->
[[$ms3_customer_sidebar]]
```

**Sidebar автоматически:**
- ✅ Получает данные клиента из сессии
- ✅ Определяет активный раздел по настройкам `ms3_customer_*_page_id`
- ✅ Подсвечивает активную ссылку
- ✅ Скрывается если клиент не авторизован

**CSS стили:**
- Встроены прямо в чанки (не требуют отдельных файлов)
- Адаптивные через media queries
- Градиенты для аватара и активной ссылки
- Совместимость с Bootstrap классами

**Упрощённый код сниппета:**
```php
// Старый код (40+ строк логики с withLayout)
if ($withLayout) {
    $chunk = $pdoFetch->getChunk($layoutTpl, [
        'content' => $content,
        'customer' => $customer->toArray(),
        'current_section' => $service,
    ]);
    return $chunk;
}
return $content;

// Новый код (1 строка)
return $pageService->render();
```

---

#### 🔄 Миграция со старой схемы

**Было:**
```modx
[[!msCustomer?
    &service=`profile`
    &withLayout=`1`
    &layoutTpl=`tpl.msCustomer.layout`
]]
```

**Стало:**

1. Создай MODX шаблон `customer.tpl`:
```html
{block 'content'}
<div class="row">
    <div class="col-md-3">[[$ms3_customer_sidebar]]</div>
    <div class="col-md-9">[[!msCustomer? &service=`profile`]]</div>
</div>
{/block}
```

2. Упрости вызов сниппета:
```modx
[[!msCustomer? &service=`profile`]]
```

**Параметры `withLayout` и `layoutTpl` больше не используются и игнорируются сниппетом.**

---

### [2025-11-23] ✨ Добавлено: Редирект после успешной авторизации/регистрации

**Контекст:** Реализована возможность настраиваемого редиректа клиентов после успешного входа или регистрации. По умолчанию - перезагрузка текущей страницы, опционально - редирект на заданную страницу (например, личный кабинет).

---

#### ✨ Новая функциональность

**1. Автоматическая авторизация после регистрации:**
- Если настройка `ms3_customer_auto_login_after_register = true` - клиент автоматически входит в аккаунт
- Токен создаётся и сохраняется в сессию
- Страница перезагружается через 1.5 секунды

**2. Настраиваемый редирект:**
- Новая системная настройка: `ms3_customer_redirect_after_login`
- Можно указать ID страницы для редиректа после входа/регистрации
- Значение `0` (по умолчанию) = остаться на текущей странице (reload)
- Значение `> 0` = редирект на указанную страницу

**3. Передача redirect_page_id через API:**
- Frontend может передать параметр `redirect_page_id` в запросе
- Приоритет: параметр запроса > системная настройка > текущая страница

---

#### 📝 Изменённые файлы

**Backend (процессоры):**

`core/components/minishop3/src/Processors/Api/Customer/Login.php`:
```php
// Определяем URL для редиректа
$redirectPageId = (int)$this->getProperty('redirect_page_id', 0);
if (!$redirectPageId) {
    $redirectPageId = (int)$this->modx->getOption('ms3_customer_redirect_after_login', null, 0);
}

$redirectUrl = '';
if ($redirectPageId > 0) {
    $redirectUrl = $this->modx->makeUrl($redirectPageId, '', '', 'full');
}

return $this->success('', [
    'customer' => [...],
    'token' => $tokenObj->get('token'),
    'expires_at' => $tokenObj->get('expires_at'),
    'redirect_url' => $redirectUrl,  // ✅ Новое поле
]);
```

`core/components/minishop3/src/Processors/Api/Customer/Register.php`:
```php
// Определяем URL для редиректа (только если автовход включен)
$redirectUrl = '';
if ($autoLogin && !$requireEmailVerification) {
    $redirectPageId = (int)$this->getProperty('redirect_page_id', 0);
    if (!$redirectPageId) {
        $redirectPageId = (int)$this->modx->getOption('ms3_customer_redirect_after_login', null, 0);
    }

    if ($redirectPageId > 0) {
        $redirectUrl = $this->modx->makeUrl($redirectPageId, '', '', 'full');
    }
}

return $this->success($this->modx->lexicon('ms3_customer_register_success'), [
    'customer' => [...],
    'token' => $tokenData,
    'email_verification_required' => $requireEmailVerification,
    'redirect_url' => $redirectUrl,  // ✅ Новое поле
]);
```

**Frontend (JavaScript):**

`assets/components/minishop3/js/web/modules/auth-forms.js`:
```javascript
// ✅ Новый метод handleRedirect
handleRedirect (responseObject) {
  // Если backend вернул redirect_url - используем его
  if (responseObject && responseObject.redirect_url) {
    window.location.href = responseObject.redirect_url
  } else {
    // Иначе перезагружаем текущую страницу
    window.location.reload()
  }
}

// Обновлено: handleLogin()
if (result.success) {
  this.showMessage('login-messages', this.getLexicon('ms3_customer_login_success'), 'success')

  setTimeout(() => {
    this.handleRedirect(result.object)  // ✅ Использует новый метод
  }, 1000)
}

// Обновлено: handleRegister()
if (result.object && result.object.token) {
  setTimeout(() => {
    this.handleRedirect(result.object)  // ✅ Использует новый метод
  }, 1500)
}
```

**Лексиконы:**

`core/components/minishop3/lexicon/ru/setting.inc.php`:
```php
$_lang['setting_ms3_customer_redirect_after_login'] = 'Страница редиректа после входа/регистрации';
$_lang['setting_ms3_customer_redirect_after_login_desc'] = 'ID страницы, на которую будет перенаправлен клиент после успешного входа или регистрации. 0 = остаться на текущей странице (перезагрузка).';
```

`core/components/minishop3/lexicon/en/setting.inc.php`:
```php
$_lang['setting_ms3_customer_redirect_after_login'] = 'Redirect page after login/registration';
$_lang['setting_ms3_customer_redirect_after_login_desc'] = 'Page ID to redirect customer after successful login or registration. 0 = stay on current page (reload).';
```

---

#### 🎯 Примеры использования

**Сценарий 1: Редирект на страницу личного кабинета**

1. Создайте страницу "Личный кабинет" (ID = 15)
2. Установите системную настройку:
   ```
   ms3_customer_redirect_after_login = 15
   ```
3. После входа/регистрации клиент автоматически попадёт на страницу с ID 15

**Сценарий 2: Остаться на текущей странице (по умолчанию)**

```
ms3_customer_redirect_after_login = 0  (или не установлено)
```
Страница просто перезагружается, клиент остаётся на той же странице

**Сценарий 3: Программный редирект через API**

```javascript
// Форма регистрации с кастомным редиректом
const result = await authForms.sendToApi('/api/v1/customer/register', {
  email: 'user@example.com',
  password: 'secret123',
  redirect_page_id: 20  // Переопределяет системную настройку
})
```

---

#### ⚙️ Технические детали

**Логика определения redirect_url:**
1. Проверяем параметр запроса `redirect_page_id`
2. Если не указан → берём из системной настройки `ms3_customer_redirect_after_login`
3. Если `> 0` → генерируем URL через `$modx->makeUrl()`
4. Если `= 0` или не указано → возвращаем пустую строку
5. Frontend проверяет `redirect_url`:
   - Если не пусто → `window.location.href = redirect_url`
   - Если пусто → `window.location.reload()`

**Особенности для регистрации:**
- Редирект работает **только** если включен автовход (`ms3_customer_auto_login_after_register = true`)
- Если требуется верификация email (`ms3_customer_require_email_verification = true`) - редиректа нет
- Иначе пользователь переключается на форму входа

---

### [2025-11-23] 🐛 Исправление: Загрузка лексиконов в процессорах авторизации

**Контекст:** Backend возвращал необработанные ключи лексикона (`ms3_customer_err_login_invalid`) вместо переведённых сообщений об ошибках.

---

#### ❌ Проблема

**Пример ответа от backend:**
```json
{
    "success": false,
    "message": "ms3_customer_err_login_invalid",
    "errors": null
}
```

**Причина:** Лексикон `customer` не загружался в контексте процессоров Login и Register. Метод `$this->modx->lexicon('ms3_customer_err_login_invalid')` возвращал необработанный ключ вместо переведённой строки.

#### ✅ Решение

Добавлена явная загрузка лексикона в начале метода `process()` обоих процессоров:

**Файлы изменены:**
- `core/components/minishop3/src/Processors/Api/Customer/Login.php`
- `core/components/minishop3/src/Processors/Api/Customer/Register.php`

**Код добавлен:**
```php
public function process()
{
    // Загружаем лексикон
    $this->modx->lexicon->load('minishop3:customer');

    // ... остальная логика процессора
}
```

**Результат:** Теперь backend возвращает переведённые сообщения:
```json
{
    "success": false,
    "message": "Неверный email или пароль",
    "errors": null
}
```

---

### [2025-11-23] 🔧 Исправление: Использование правильного API endpoint для форм авторизации

**Контекст:** Формы авторизации/регистрации обращались к `connector.php` (менеджер MODX), который требует авторизацию админа и возвращал 401 ошибку. Исправлено на использование `api.php` - правильного frontend API endpoint.

---

#### ❌ Проблема

**Ошибка 401 - Доступ запрещён:**
- Формы отправляли запросы к `/assets/components/minishop3/connector.php`
- `connector.php` - это коннектор для **менеджера MODX**, требует авторизацию админа
- Login/Register процессоры должны быть **публичными**, иначе невозможно войти

**Неправильный запрос:**
```
POST /assets/components/minishop3/connector.php
Content-Type: multipart/form-data
action=MiniShop3\Processors\Api\Customer\Login

→ 401 Forbidden
```

#### ✅ Решение

**1. Добавлены публичные роуты в `config/routes/web.php`:**
```php
// POST /api/v1/customer/login - Вход клиента
$router->post('/login', function($params) use ($modx) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: [];

    $response = $modx->runProcessor(
        'MiniShop3\Processors\Api\Customer\Login',
        [
            'email' => $data['email'] ?? '',
            'password' => $data['password'] ?? ''
        ]
    );

    if ($response->isError()) {
        return Response::error($response->getMessage(), 400);
    }

    return Response::success($response->getObject(), $response->getMessage());
});

// POST /api/v1/customer/register - Регистрация клиента
// ... аналогично
```

**2. Обновлён `auth-forms.js`:**
- Изменён метод `sendToConnector()` → `sendToApi()`
- Используется `api.php` вместо `connector.php`
- Отправка JSON вместо FormData
- Роуты вместо action параметра

**Было:**
```javascript
async sendToConnector(action, data) {
    const formData = new FormData()
    formData.append('action', action)
    // ...
    fetch(connectorUrl, { body: formData })
}
```

**Стало:**
```javascript
async sendToApi(route, data) {
    const url = new URL(this.config.apiUrl, window.location.origin)
    url.searchParams.set('route', route)

    fetch(url.toString(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
}
```

**3. Обновлён шаблон `ms3_customer_unauthorized.tpl`:**
```javascript
// Было
const authForms = new AuthForms({
    connectorUrl: '/assets/components/minishop3/connector.php',
    loginAction: 'MiniShop3\\Processors\\Api\\Customer\\Login',
    registerAction: 'MiniShop3\\Processors\\Api\\Customer\\Register'
})

// Стало
const authForms = new AuthForms({
    apiUrl: '/assets/components/minishop3/api.php',
    loginRoute: '/api/v1/customer/login',
    registerRoute: '/api/v1/customer/register'
})
```

---

#### 📁 Изменённые файлы

1. **`config/routes/web.php`**
   - Добавлены публичные роуты `/api/v1/customer/login` и `/api/v1/customer/register`
   - Парсинг JSON body через `file_get_contents('php://input')`
   - Вызов MODX процессоров через `$modx->runProcessor()`

2. **`assets/components/minishop3/js/web/modules/auth-forms.js`**
   - Метод `sendToConnector()` переименован в `sendToApi()`
   - Изменён формат отправки: JSON вместо FormData
   - Параметры: `apiUrl`, `loginRoute`, `registerRoute`

3. **`ms3_customer_unauthorized.tpl`**
   - Обновлена инициализация AuthForms с новыми параметрами

---

#### 💡 Архитектура

**Разделение коннекторов:**
- **`connector.php`** - для менеджера MODX (требует авторизацию админа)
- **`api.php`** - для фронтенда (публичный API)

**Правильные запросы:**
```
✅ POST /assets/components/minishop3/api.php?route=/api/v1/customer/login
Content-Type: application/json
{"email": "user@example.com", "password": "12345678"}

✅ POST /assets/components/minishop3/api.php?route=/api/v1/customer/register
Content-Type: application/json
{"email": "user@example.com", "password": "12345678", ...}
```

---

### [2025-11-23] 🐛 Исправление: Синтаксис Fenom в шаблоне форм авторизации

**Контекст:** Исправлен неправильный синтаксис обращения к MODX API в Fenom шаблоне. Использованы правильные модификаторы Fenom вместо PHP вызовов.

---

#### ❌ Проблема

В шаблоне использовались PHP вызовы, которые не работают в Fenom:
```fenom
{* НЕПРАВИЛЬНО *}
<script src="{$_modx->getOption('assets_url')}..."></script>
window.ms3Lexicon.key = '{$_modx->lexicon("key")}';
```

**Ошибка:** В Fenom нет переменной `$_modx` и метода `getOption()`. Fenom - это шаблонизатор со своим синтаксисом.

#### ✅ Исправление

Использованы правильные модификаторы Fenom:
```fenom
{* ПРАВИЛЬНО *}
<script src="{'assets_url' | option}..."></script>
window.ms3Lexicon.key = '{'key' | lexicon}';
```

**Изменения:**
- `$_modx->getOption('assets_url')` → `{'assets_url' | option}`
- `$_modx->lexicon("key")` → `{'key' | lexicon}`

**Файл:** `core/components/minishop3/elements/chunks/ms3_customer_unauthorized.tpl`

**Количество исправлений:** 9 мест (1 для assets_url, 7 для лексиконов, 1 для connector_url)

---

### [2025-11-23] ♻️ Рефакторинг: Вынесение JavaScript форм авторизации в отдельный модуль

**Контекст:** JavaScript логика форм авторизации/регистрации вынесена из шаблона `ms3_customer_unauthorized.tpl` в отдельный модуль `auth-forms.js` для лучшей организации кода и возможности переиспользования.

---

#### 🎯 Что сделано

**1. Создан модуль `assets/components/minishop3/js/web/modules/auth-forms.js`:**
- ✅ ES6 класс `AuthForms` с полным функционалом обработки форм
- ✅ Методы: `handleLogin()`, `handleRegister()`, `handleForgotPassword()`
- ✅ Утилиты: `showMessage()`, `clearMessages()`, `setButtonLoading()`, `serializeForm()`
- ✅ Отправка данных через `sendToConnector()` к MODX процессорам
- ✅ Fallback для табов (если нет Bootstrap JS)
- ✅ Поддержка глобального объекта `window.ms3Lexicon` для переводов
- ✅ Fallback лексиконов на английском языке

**2. Обновлён шаблон `ms3_customer_unauthorized.tpl`:**
- ✅ Удалён весь JavaScript код (>200 строк)
- ✅ Оставлены только: HTML, CSS, подключение скрипта и минимальная инициализация
- ✅ Лексиконы передаются через `window.ms3Lexicon`
- ✅ Инициализация AuthForms с конфигурацией:
  ```javascript
  const authForms = new AuthForms({
      connectorUrl: '/assets/components/minishop3/connector.php',
      loginAction: 'MiniShop3\\Processors\\Api\\Customer\\Login',
      registerAction: 'MiniShop3\\Processors\\Api\\Customer\\Register'
  })
  authForms.init()
  ```

**3. Архитектура:**
- **Модульность:** JavaScript вынесен в отдельный файл, можно переиспользовать
- **Расширяемость:** Класс AuthForms может быть расширен или переопределён
- **Глобальный доступ:** `window.AuthForms` доступен для использования в других скриптах
- **i18n:** Лексиконы передаются через `window.ms3Lexicon` из PHP

---

#### 📁 Изменённые файлы

1. **`assets/components/minishop3/js/web/modules/auth-forms.js`** (создан)
   - 430+ строк кода
   - Полный функционал обработки форм авторизации/регистрации

2. **`core/components/minishop3/elements/chunks/ms3_customer_unauthorized.tpl`**
   - Удалено: ~200 строк JavaScript логики
   - Добавлено: подключение auth-forms.js и инициализация (25 строк)
   - Размер уменьшен с ~480 строк до ~270 строк

---

#### 💡 Преимущества

- ✅ **Разделение ответственности:** HTML/CSS в шаблоне, JavaScript в отдельном модуле
- ✅ **Переиспользование:** AuthForms можно использовать на других страницах
- ✅ **Тестирование:** Класс AuthForms легче покрыть unit-тестами
- ✅ **Кэширование:** Браузер кэширует .js файл отдельно от шаблона
- ✅ **Модульность:** Можно заменить/расширить AuthForms не трогая шаблон
- ✅ **ES6:** Современный синтаксис JavaScript (class, async/await, const/let)

---

### [2025-11-23] ✨ Добавлено: Объединённая форма авторизации и регистрации с табами

**Контекст:** Страница приглашения к авторизации (`tpl.msCustomer.unauthorized`) теперь содержит обе формы (Login и Register) на одной странице с переключением через табы. Используется AJAX для отправки данных, без перехода на отдельные страницы.

---

#### 🎯 Что сделано

**1. Обновлён шаблон `ms3_customer_unauthorized.tpl`:**
- ✅ Добавлены табы для переключения между "Войти" и "Регистрация"
- ✅ Форма авторизации (email, password, remember me, forgot password link)
- ✅ Форма регистрации (email, first_name, last_name, phone, password, password_confirm, privacy_accepted)
- ✅ AJAX отправка через Fetch API к процессорам через connector.php
- ✅ Валидация на клиенте (проверка совпадения паролей, обязательные поля)
- ✅ Индикатор загрузки (спиннер) при отправке формы
- ✅ Отображение ошибок/успешных сообщений без перезагрузки
- ✅ Автоматическая перезагрузка после успешного входа/регистрации
- ✅ Fallback для табов если нет Bootstrap JS
- ✅ Адаптивный дизайн (Bootstrap классы)

**2. Добавлены лексиконы (RU + EN):**

**Русский (`core/components/minishop3/lexicon/ru/customer.inc.php`):**
```php
$_lang['ms3_customer_account'] = 'Личный кабинет';
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
```

**Английский (`core/components/minishop3/lexicon/en/customer.inc.php`):**
- Все аналогичные ключи переведены на английский

**3. Архитектура:**
- Используются существующие процессоры:
  - `MiniShop3\Processors\Api\Customer\Login` - для входа
  - `MiniShop3\Processors\Api\Customer\Register` - для регистрации
- Процессоры вызываются через `connector.php` с параметром `action`
- После успешной авторизации/регистрации сессия устанавливается, страница перезагружается
- При регистрации с автовходом (настройка `ms3_customer_auto_login_after_register`) пользователь сразу входит
- При регистрации БЕЗ автовхода форма переключается на таб "Войти" через 2 секунды

---

#### 📁 Изменённые файлы

1. **`core/components/minishop3/elements/chunks/ms3_customer_unauthorized.tpl`**
   - Полностью переписан шаблон
   - Добавлены формы с валидацией
   - Добавлен JavaScript для AJAX и переключения табов
   - Добавлены CSS стили для красивого оформления

2. **`core/components/minishop3/lexicon/ru/customer.inc.php`**
   - Добавлено 11 новых лексиконов для форм

3. **`core/components/minishop3/lexicon/en/customer.inc.php`**
   - Добавлено 11 новых лексиконов (английские переводы)

---

#### 💡 Преимущества

- ✅ **UX улучшен:** Не нужно переходить на отдельные страницы для входа/регистрации
- ✅ **Меньше запросов:** AJAX вместо полной перезагрузки страницы
- ✅ **Современный интерфейс:** Табы, анимации, спиннеры загрузки
- ✅ **Валидация:** Проверка на клиенте перед отправкой (совпадение паролей, обязательные поля)
- ✅ **Безопасность:** Используются существующие процессоры с RateLimiter защитой
- ✅ **Кроссбраузерность:** Fallback для табов если нет Bootstrap JS
- ✅ **i18n ready:** Все тексты через лексиконы (RU + EN)

---

#### 🧪 Тестирование

**Сценарии для проверки:**
1. Открыть страницу личного кабинета без авторизации → видим табы Login/Register
2. Попытка войти с пустыми полями → показывается ошибка
3. Попытка войти с неверными данными → показывается ошибка от процессора
4. Успешный вход → показывается успешное сообщение, перезагрузка через 1 сек
5. Регистрация с несовпадающими паролями → показывается ошибка
6. Регистрация без согласия с политикой → показывается ошибка
7. Успешная регистрация (автовход включен) → успешное сообщение, перезагрузка через 1.5 сек
8. Успешная регистрация (автовход выключен) → успешное сообщение, переключение на таб Login через 2 сек
9. Переключение между табами работает корректно
10. Индикатор загрузки появляется при отправке

---

### [2025-11-23] 🐛 Исправление: Использование правильных имён чанков из БД

**Контекст:** При установке компонента чанки регистрируются в БД с именами из `_build/elements/chunks.php` (например, `tpl.msCustomer.profile`), а не с именами файлов (например, `ms3_customer_profile`). Все сервисы и сниппет обновлены для использования правильных имён.

---

#### 🐛 Проблема

**Ошибка:**
- В коде использовались имена файлов чанков: `ms3_customer_profile`, `ms3_customer_addresses` и т.д.
- MODX ищет чанки в БД по именам, указанным в `chunks.php`: `tpl.msCustomer.profile`, `tpl.msCustomer.addresses`
- При вызове `$pdoFetch->getChunk('ms3_customer_profile', $data)` MODX не находит чанк в БД
- Результат: пустой вывод или ошибка "Chunk not found"

**Соответствие имён файлов и чанков в БД:**
| Файл | Имя в БД (chunks.php) |
|------|----------------------|
| `ms3_customer_layout.tpl` | `tpl.msCustomer.layout` |
| `ms3_customer_unauthorized.tpl` | `tpl.msCustomer.unauthorized` |
| `ms3_customer_profile.tpl` | `tpl.msCustomer.profile` |
| `ms3_customer_addresses.tpl` | `tpl.msCustomer.addresses` |
| `ms3_customer_address_row.tpl` | `tpl.msCustomer.address.row` |
| `ms3_customer_address_form.tpl` | `tpl.msCustomer.address.form` |
| `ms3_customer_orders.tpl` | `tpl.msCustomer.orders` |
| `ms3_customer_order_row.tpl` | `tpl.msCustomer.order.row` |
| `ms3_customer_order_details.tpl` | `tpl.msCustomer.order.details` |

---

#### ✅ Исправление

**Файлы изменены:**
- `core/components/minishop3/elements/snippets/ms3_customer.php` (2 места)
- `core/components/minishop3/src/Services/Customer/CustomerPageService.php` (1 место)
- `core/components/minishop3/src/Services/Customer/ProfilePageService.php` (1 место)
- `core/components/minishop3/src/Services/Customer/AddressesPageService.php` (4 места)
- `core/components/minishop3/src/Services/Customer/OrdersPageService.php` (3 места)

**Изменения:**

1. **ms3_customer.php:**
```php
// Было
$unauthorizedTpl = $modx->getOption('unauthorizedTpl', $scriptProperties, 'ms3_customer_unauthorized');
$layoutTpl = $modx->getOption('layoutTpl', $scriptProperties, 'ms3_customer_layout');

// Стало
$unauthorizedTpl = $modx->getOption('unauthorizedTpl', $scriptProperties, 'tpl.msCustomer.unauthorized');
$layoutTpl = $modx->getOption('layoutTpl', $scriptProperties, 'tpl.msCustomer.layout');
```

2. **CustomerPageService::renderUnauthorized():**
```php
// Было: 'ms3_customer_unauthorized'
// Стало: 'tpl.msCustomer.unauthorized'
```

3. **ProfilePageService::render():**
```php
// Было: 'ms3_customer_profile'
// Стало: 'tpl.msCustomer.profile'
```

4. **AddressesPageService:**
```php
// renderList()
// Было: 'ms3_customer_addresses', 'ms3_customer_address_row'
// Стало: 'tpl.msCustomer.addresses', 'tpl.msCustomer.address.row'

// renderCreateForm() и renderEditForm()
// Было: 'ms3_customer_address_form'
// Стало: 'tpl.msCustomer.address.form'
```

5. **OrdersPageService:**
```php
// renderOrdersList()
// Было: 'ms3_customer_orders', 'ms3_customer_order_row'
// Стало: 'tpl.msCustomer.orders', 'tpl.msCustomer.order.row'

// renderOrderDetails()
// Было: 'ms3_customer_order_details'
// Стало: 'tpl.msCustomer.order.details'
```

---

#### 📊 Итоги

**Заменено:** 11 имён чанков
- Сниппет: 2
- CustomerPageService: 1
- ProfilePageService: 1
- AddressesPageService: 4
- OrdersPageService: 3

**Результаты:**
- ✅ MODX теперь находит все чанки в БД
- ✅ Корректный рендеринг всех страниц личного кабинета
- ✅ Соответствие с `_build/elements/chunks.php`
- ✅ Единообразие с другими сниппетами MiniShop3

**Важное правило:**
- **ВСЕГДА** используй имена чанков из `_build/elements/chunks.php`, а не имена файлов
- Имена в БД обычно следуют паттерну `tpl.ComponentName.chunkName`
- Имена файлов используются только для разработки и хранения исходников

---

### [2025-11-23] 🐛 Исправление: Использование pdoFetch->getChunk() вместо modx->getChunk()

**Контекст:** MODX не умеет парсить Fenom синтаксис в чанках. Все вызовы `$modx->getChunk()` заменены на `$pdoFetch->getChunk()` для корректного рендеринга Fenom шаблонов.

---

#### 🐛 Проблема

**Ошибка:**
- `$modx->getChunk()` не обрабатывает Fenom синтаксис (`{if}`, `{foreach}`, `{$var}`, модификаторы)
- Чанки с Fenom конструкциями возвращались как обычный текст без парсинга
- Переменные не заменялись, условия не работали

**Пример:**
```fenom
{* Чанк ms3_customer_profile.tpl *}
{if $email_verified}
    <span class="badge">{'ms3_customer_email_verified' | lexicon}</span>
{/if}
```

**С $modx->getChunk():**
```html
{if $email_verified}
    <span class="badge">{'ms3_customer_email_verified' | lexicon}</span>
{/if}
```
↑ Вывод как есть, без обработки!

**С $pdoFetch->getChunk():**
```html
<span class="badge">Email подтверждён</span>
```
↑ Правильная обработка Fenom + лексиконов!

---

#### ✅ Исправление

**Файлы изменены:**
- `core/components/minishop3/src/Services/Customer/CustomerPageService.php` (1 место)
- `core/components/minishop3/src/Services/Customer/ProfilePageService.php` (1 место)
- `core/components/minishop3/src/Services/Customer/AddressesPageService.php` (3 места)
- `core/components/minishop3/src/Services/Customer/OrdersPageService.php` (3 места)
- `core/components/minishop3/elements/snippets/ms3_customer.php` (2 места)

**Изменения:**

1. **В сниппете ms3_customer.php:**
```php
// Добавлена загрузка pdoTools
use ModxPro\PdoTools\Fetch;

/** @var Fetch $pdoFetch */
$pdoFetch = $modx->services->get(Fetch::class);

// Заменены все вызовы
// Было: $modx->getChunk($tpl, $data)
// Стало: $pdoFetch->getChunk($tpl, $data)
```

2. **В Service классах:**
```php
// CustomerPageService::renderUnauthorized()
$chunk = $this->pdoFetch->getChunk($tpl, [...]);

// ProfilePageService::render()
$chunk = $this->pdoFetch->getChunk($tpl, $data);

// AddressesPageService::renderList()
$chunk = $this->pdoFetch->getChunk($addressTpl, $addressData);
$chunk = $this->pdoFetch->getChunk($tpl, $data);

// AddressesPageService::renderCreateForm()
$chunk = $this->pdoFetch->getChunk($tpl, $data);

// AddressesPageService::renderEditForm()
$chunk = $this->pdoFetch->getChunk($tpl, $data);

// OrdersPageService::renderOrdersList()
$chunk = $this->pdoFetch->getChunk($orderTpl, $orderData);
$chunk = $this->pdoFetch->getChunk($tpl, $data);

// OrdersPageService::renderOrderDetails()
$chunk = $this->pdoFetch->getChunk($tpl, $data);
```

---

#### 📊 Итоги

**Заменено вызовов:** 10
- Сниппет: 2
- CustomerPageService: 1
- ProfilePageService: 1
- AddressesPageService: 3
- OrdersPageService: 3

**Результаты:**
- ✅ Корректная обработка Fenom синтаксиса во всех чанках
- ✅ Работают условия `{if}`, циклы `{foreach}`, модификаторы
- ✅ Правильная замена переменных `{$var}`
- ✅ Лексиконы обрабатываются через `{'key' | lexicon}`
- ✅ Единообразие с другими сниппетами MiniShop3 (ms3_cart, ms3_order)

**Важно:**
- В MiniShop3 **ВСЕГДА** используй `$pdoFetch->getChunk()` для рендеринга Fenom чанков
- `$modx->getChunk()` подходит только для простых чанков без Fenom синтаксиса

---

### [2025-11-23] 🔧 Улучшение: Поддержка возврата сырых данных (&return=data) в сниппете msCustomer

**Контекст:** Добавлена поддержка параметра `&return=data` для получения сырых данных без рендеринга HTML. Это необходимо для вызова сниппета из CLI, API endpoints, тестов и других программных контекстов.

---

#### ✨ Добавлено

**1. Абстрактный метод getData() в базовом сервисе**

**Файлы:**
- `core/components/minishop3/src/Services/Customer/CustomerPageService.php` (строка 148-153)

**Назначение:**
- Получение сырых данных страницы без HTML рендеринга
- Все дочерние сервисы обязаны реализовать этот метод
- Единообразный интерфейс для всех страниц личного кабинета

```php
/**
 * Получить сырые данные страницы без рендеринга (для CLI, API, тестов)
 *
 * @return array Данные страницы
 */
abstract public function getData(): array;
```

**2. Реализация getData() в ProfilePageService**

**Файлы:**
- `core/components/minishop3/src/Services/Customer/ProfilePageService.php` (строки 28-55)

**Возвращаемые данные:**
```php
[
    'customer' => [...],           // Данные клиента
    'email_verified' => true,      // Статус подтверждения email
    'email_verified_at' => '...',  // Дата подтверждения
    'phone_verified' => false,     // Статус подтверждения телефона
    'phone_verified_at' => null,   // Дата подтверждения телефона
    'errors' => [],                // Ошибки валидации
    'success' => false,            // Флаг успешного сохранения
]
```

**3. Реализация getData() в AddressesPageService**

**Файлы:**
- `core/components/minishop3/src/Services/Customer/AddressesPageService.php` (строки 35-105)

**Особенности:**
- Поддержка 3 режимов: list, create, edit
- Автоматическое формирование `display_name` для адресов
- Разные наборы данных в зависимости от режима

**Возвращаемые данные:**

```php
// Режим list
[
    'mode' => 'list',
    'customer' => [...],
    'addresses' => [
        ['id' => 1, 'city' => 'Москва', 'display_name' => 'Москва, ...'],
        // ...
    ],
    'addresses_count' => 5,
    'success' => 'Адрес успешно сохранён',
    'error' => null,
]

// Режим create/edit
[
    'mode' => 'create',
    'customer' => [...],
    'address' => [...],  // Данные адреса для редактирования
    'errors' => ['city' => 'Поле обязательно для заполнения'],
]
```

**4. Реализация getData() в OrdersPageService**

**Файлы:**
- `core/components/minishop3/src/Services/Customer/OrdersPageService.php` (строки 39-49, 283-406)

**Вспомогательные методы:**
- `getOrdersListData()` - данные списка заказов с пагинацией
- `getOrderDetailsData($orderId)` - детальные данные конкретного заказа

**Возвращаемые данные:**

```php
// Список заказов
[
    'orders' => [
        [
            'id' => 123,
            'createdon_formatted' => '23.11.2025 14:30',
            'cost_formatted' => '1 500 ₽',
            'status_name' => 'Новый',
            'status_color' => '#28a745',
        ],
        // ...
    ],
    'orders_count' => 10,
    'total' => 25,
    'statuses' => [...],      // Список статусов для фильтра
    'pagination' => [...],    // Данные пагинации
    'customer' => [...],
]

// Детали заказа
[
    'order' => [...],         // Данные заказа
    'products' => [...],      // Товары заказа с опциями
    'delivery' => [...],      // Способ доставки
    'payment' => [...],       // Способ оплаты
    'address' => [...],       // Адрес доставки
    'total' => [              // Форматированные итоги
        'cost' => '1 500 ₽',
        'cart_cost' => '1 200 ₽',
        'delivery_cost' => '300 ₽',
        'weight' => '2.5 кг',
    ],
    'customer' => [...],
]
```

**5. Поддержка &return=data в сниппете msCustomer**

**Файлы:**
- `core/components/minishop3/elements/snippets/ms3_customer.php` (строки 39, 55-84)

**Изменения:**
```php
$return = $modx->getOption('return', $scriptProperties, 'tpl');

// Проверить авторизацию
if (!$pageService->checkAuth()) {
    if ($return === 'data') {
        return [
            'authorized' => false,
            'login_url' => '...',
            'register_url' => '...',
        ];
    }
    return $pageService->renderUnauthorized();
}

// Вернуть сырые данные (для CLI, API, тестов)
if ($return === 'data') {
    $data = $pageService->getData();
    $data['authorized'] = true;
    $data['service'] = $service;

    if ($withLayout) {
        $data['with_layout'] = true;
        $data['current_section'] = $service;
    }

    return $data;
}

// Иначе рендеринг HTML
$content = $pageService->render();
// ...
```

**6. Публичные методы checkAuth() и renderUnauthorized()**

**Файлы:**
- `core/components/minishop3/src/Services/Customer/CustomerPageService.php` (строки 69, 121)

**Изменения:**
- `protected function checkAuth()` → `public function checkAuth()`
- `protected function renderUnauthorized()` → `public function renderUnauthorized()`

**Причина:**
Сниппет должен иметь возможность проверить авторизацию и отрендерить unauthorized страницу до вызова `getData()` или `render()`.

---

#### 🎯 Примеры использования

**Пример 1: Получение данных профиля из CLI**

```bash
# CLI скрипт
php -r "
require_once '/path/to/modx/index.php';
\$modx = new modX();
\$modx->initialize('web');

\$data = \$modx->runSnippet('msCustomer', [
    'service' => 'profile',
    'return' => 'data',
]);

print_r(\$data);
"
```

**Результат:**
```php
Array
(
    [customer] => Array
    (
        [id] => 5
        [email] => user@example.com
        [first_name] => Иван
        [last_name] => Иванов
        [email_verified_at] => 2025-11-23 10:30:00
        // ...
    )
    [email_verified] => 1
    [email_verified_at] => 23.11.2025 10:30
    [authorized] => 1
    [service] => profile
)
```

**Пример 2: Получение списка заказов для REST API**

```php
// В API контроллере
public function getCustomerOrders(Request $request): array
{
    // Установить сессию клиента
    $_SESSION['ms3']['customer_id'] = $request->getCustomerId();

    // Вызвать сниппет для получения данных
    $data = $this->modx->runSnippet('msCustomer', [
        'service' => 'orders',
        'return' => 'data',
        'limit' => 10,
    ]);

    return [
        'success' => true,
        'data' => $data,
    ];
}
```

**Пример 3: Юнит-тест для проверки валидации адресов**

```php
class AddressesPageServiceTest extends TestCase
{
    public function testGetDataReturnsAddressList()
    {
        // Подготовить тестовую сессию
        $_SESSION['ms3']['customer_id'] = 123;

        // Вызвать сниппет
        $data = $this->modx->runSnippet('msCustomer', [
            'service' => 'addresses',
            'return' => 'data',
        ]);

        // Проверить структуру данных
        $this->assertArrayHasKey('addresses', $data);
        $this->assertArrayHasKey('addresses_count', $data);
        $this->assertArrayHasKey('customer', $data);
    }
}
```

**Пример 4: Получение данных для кастомного рендеринга**

```php
// Кастомный сниппет для генерации PDF отчёта о заказах
$ordersData = $modx->runSnippet('msCustomer', [
    'service' => 'orders',
    'return' => 'data',
]);

// Использовать данные для генерации PDF
$pdf = new TCPDF();
foreach ($ordersData['orders'] as $order) {
    $pdf->AddPage();
    $pdf->Write(0, "Заказ #{$order['id']}");
    $pdf->Write(0, "Дата: {$order['createdon_formatted']}");
    // ...
}
$pdf->Output('orders.pdf', 'D');
```

---

#### 🏗️ Архитектурное решение

**Паттерн: Data Transfer Object (DTO)**

Метод `getData()` возвращает структурированные данные, готовые к использованию:
- В API endpoints (JSON response)
- В CLI скриптах (array manipulation)
- В юнит-тестах (assertions)
- В кастомных рендерерах (PDF, Excel, и т.д.)

**Принцип DRY (Don't Repeat Yourself)**

Метод `render()` использует `getData()` для получения данных:
```php
public function render(): string
{
    $data = $this->getData();  // Переиспользование логики

    // Очистить flash-сообщения
    unset($_SESSION['ms3']['customer_profile_errors']);

    $chunk = $this->modx->getChunk($tpl, $data);
    return is_string($chunk) ? $chunk : '';
}
```

**Единообразие с другими сниппетами**

Параметр `&return=data` работает идентично в:
- `ms3_cart.php` (строка 48, 200)
- `ms3_order.php` (строка 300)
- `ms3_customer.php` (строка 39, 72) ← **НОВОЕ**

---

#### 📊 Итоги

**Добавлено кода:**
- CustomerPageService: +7 строк (абстрактный метод + public модификаторы)
- ProfilePageService: +28 строк (метод getData)
- AddressesPageService: +71 строк (метод getData с 3 режимами)
- OrdersPageService: +129 строк (getData + 2 вспомогательных метода)
- ms3_customer.php: +32 строки (поддержка &return=data)
- **Итого:** ~267 строк

**Результаты:**
- ✅ Единообразие с другими сниппетами MiniShop3
- ✅ Возможность вызова из CLI без overhead HTML рендеринга
- ✅ Упрощение написания REST API endpoints
- ✅ Удобство юнит-тестирования
- ✅ Гибкость для кастомных рендереров (PDF, Excel, и т.д.)

---

### [2025-11-23] ✅ ЭТАП 3: Личный кабинет клиента (Customer Account Pages) - ЗАВЕРШЁН

**Контекст:** Реализован полноценный личный кабинет клиента с тремя страницами: профиль, адреса, история заказов. Использован серверный рендеринг (SSR) через Fenom, правильная архитектура с разделением на сервисы.

---

#### 📋 Итоговая сводка реализованного функционала

**1. Архитектура личного кабинета**
- ✅ Базовый сервис `CustomerPageService` - общая логика для всех страниц
- ✅ Специализированные сервисы:
  - `ProfilePageService` - профиль клиента
  - `AddressesPageService` - управление адресами
  - `OrdersPageService` - история заказов
- ✅ Сниппет `ms3_customer.php` - роутинг и проверка авторизации
- ✅ Template inheritance через Fenom `{extends}`

**2. Страница профиля (Profile)**
- ✅ Просмотр личных данных (имя, фамилия, email, телефон)
- ✅ Статус подтверждения email (verified badge)
- ✅ Кнопка повторной отправки письма подтверждения
- ✅ Форма редактирования профиля (планируется)
- ✅ Шаблон: `ms3_customer_profile.tpl`

**3. Страница адресов (Addresses)**
- ✅ Список сохранённых адресов
- ✅ Режимы: list, create, edit
- ✅ Автоматическое именование адресов (город, улица, дом)
- ✅ Кнопки: редактировать, удалить, сделать по умолчанию
- ✅ Flash-сообщения (success/error через сессию)
- ✅ Шаблоны: `ms3_customer_addresses.tpl`, `ms3_customer_address_row.tpl`, `ms3_customer_address_form.tpl`

**4. Страница заказов (Orders)**
- ✅ Список заказов с пагинацией (limit: 20 по умолчанию)
- ✅ Фильтрация по статусу заказа
- ✅ Детальный просмотр заказа (товары, доставка, оплата, адрес)
- ✅ Форматирование цен и дат
- ✅ JOIN к статусам для отображения названия и цвета
- ✅ Расчёт скидок для товаров
- ✅ Шаблоны: `ms3_customer_orders.tpl`, `ms3_customer_order_row.tpl`, `ms3_customer_order_details.tpl`

**5. Шаблоны и Layout**
- ✅ Главный шаблон: `ms3_customer_account.tpl` (templates/, extends base.tpl)
- ✅ Layout чанк: `ms3_customer_layout.tpl` - сайдбар с навигацией
- ✅ Unauthorized шаблон: `ms3_customer_unauthorized.tpl` - форма входа/регистрации
- ✅ Bootstrap 5 стилизация
- ✅ Адаптивный дизайн (sidebar + content)

**6. Исправления архитектуры**
- ✅ Перемещение шаблона из chunks/ в templates/
- ✅ Правильное использование лексиконов (загрузка в сниппете)
- ✅ Замена `pdoFetch->getChunk()` на `$modx->getChunk()`
- ✅ Исправление синтаксиса Fenom (`{if $errors.field?}` вместо `{if 'field' in list $errors}`)
- ✅ Перенос лексиконов из JavaScript в data-атрибуты

---

#### 🎯 Архитектурные решения

**1. SSR (Server-Side Rendering)**
- Весь HTML рендерится на сервере через Fenom
- JavaScript только для улучшения UX (AJAX удаление адресов, и т.д.)
- Преимущества: SEO, быстрая первая отрисовка, работает без JS

**2. Service Layer Pattern**
```php
abstract class CustomerPageService {
    abstract public function render(): string;
    public function process(): string {
        if (!$this->checkAuth()) {
            return $this->renderUnauthorized();
        }
        return $this->render();
    }
}

class ProfilePageService extends CustomerPageService {
    public function render(): string {
        // Рендеринг страницы профиля
    }
}
```

**3. Роутинг через сниппет**
```php
// ms3_customer.php
$service = match ($_GET['service'] ?? 'profile') {
    'profile' => ProfilePageService::class,
    'addresses' => AddressesPageService::class,
    'orders' => OrdersPageService::class,
};

$pageService = new $serviceClass($modx, $ms3, $scriptProperties);
$content = $pageService->process();
```

**4. Template Inheritance (Fenom)**
```fenom
{* ms3_customer_account.tpl *}
{extends 'file:templates/base.tpl'}

{block 'pagecontent'}
<div class="customer-account">
    [[!msCustomer?
        &service=`[[+service:default=`profile`]]`
        &withLayout=`1`
    ]]
</div>
{/block}
```

**5. Layout Wrapper Pattern**
```fenom
{* ms3_customer_layout.tpl *}
<div class="row">
    <div class="col-lg-3">
        {* Sidebar navigation *}
    </div>
    <div class="col-lg-9">
        {$content}  {* Контент от сервиса *}
    </div>
</div>
```

**6. Правильное использование лексиконов**
```php
// Сниппет загружает лексиконы
$modx->lexicon->load('minishop3:customer');

// Шаблон использует лексиконы
{'ms3_customer_profile_title' | lexicon}

// JavaScript читает из data-атрибутов
<div data-confirm-delete="{'ms3_customer_address_delete_confirm' | lexicon}">
```

---

#### 📂 Файлы созданы/изменены

**Templates:**
- `core/components/minishop3/elements/templates/ms3_customer_account.tpl` ✨ NEW (перемещён из chunks/)

**Chunks:**
- `core/components/minishop3/elements/chunks/ms3_customer_layout.tpl` ✨ NEW
- `core/components/minishop3/elements/chunks/ms3_customer_unauthorized.tpl` ✨ NEW
- `core/components/minishop3/elements/chunks/ms3_customer_profile.tpl` 🔄 MODIFIED (исправлен Fenom)
- `core/components/minishop3/elements/chunks/ms3_customer_addresses.tpl` ✨ NEW
- `core/components/minishop3/elements/chunks/ms3_customer_address_row.tpl` 🔄 MODIFIED (лексиконы в data-*)
- `core/components/minishop3/elements/chunks/ms3_customer_address_form.tpl` 🔄 MODIFIED (исправлен Fenom)
- `core/components/minishop3/elements/chunks/ms3_customer_orders.tpl` ✨ NEW
- `core/components/minishop3/elements/chunks/ms3_customer_order_row.tpl` ✨ NEW
- `core/components/minishop3/elements/chunks/ms3_customer_order_details.tpl` ✨ NEW
- `core/components/minishop3/elements/chunks/ms3_customer_account_simple.tpl` ✨ NEW (простой вариант без layout)

**Snippets:**
- `core/components/minishop3/elements/snippets/ms3_customer.php` 🔄 MODIFIED (правильная архитектура)

**Services:**
- `core/components/minishop3/src/Services/Customer/CustomerPageService.php` ✨ NEW
- `core/components/minishop3/src/Services/Customer/ProfilePageService.php` ✨ NEW
- `core/components/minishop3/src/Services/Customer/AddressesPageService.php` ✨ NEW
- `core/components/minishop3/src/Services/Customer/OrdersPageService.php` ✨ NEW

**Build:**
- `_build/elements/chunks.php` 🔄 MODIFIED (добавлены чанки, удалён ms3_customer_account)

---

#### 🐛 Исправленные ошибки

**1. Шаблон в неправильной директории**
- ❌ Проблема: Полный HTML шаблон (с DOCTYPE) был в chunks/
- ✅ Решение: Перемещён в templates/, из chunks.php удалён

**2. Лексиконы недоступны в шаблоне**
- ❌ Проблема: Template пытался использовать лексиконы до загрузки сниппетом
- ✅ Решение: Создан ms3_customer_layout.tpl chunk, лексиконы используются внутри chunks

**3. Неправильный синтаксис Fenom**
- ❌ Проблема: `{if 'field' in list $errors}` - такого синтаксиса не существует
- ✅ Решение: Заменено на `{if $errors.field?}` во всех чанках

**4. Лексиконы в JavaScript строках**
- ❌ Проблема: `alert('{'key' | lexicon}')` не работает
- ✅ Решение: Лексиконы перенесены в data-атрибуты, JS читает из DOM

**5. getChunk() возвращает массив вместо строки**
- ❌ Проблема: `pdoFetch->getChunk()` возвращал Array, вызывал "Array ( [key] => value )" в output
- ✅ Решение: Заменено на `$modx->getChunk()` + проверка `is_string($chunk) ? $chunk : ''`
- ✅ Исправлено в 4 файлах: CustomerPageService, ProfilePageService, AddressesPageService (3 метода), OrdersPageService (2 метода)

**6. Авторизация проверялась в неправильном месте**
- ❌ Проблема: Проверка auth в Service классах, дублирование логики
- ✅ Решение: Проверка перенесена в начало сниппета (строка 19), сервисы упрощены

**7. Отсутствующая регистрация chunk**
- ❌ Проблема: ms3_customer_layout chunk не был зарегистрирован в chunks.php
- ✅ Решение: Добавлен в chunks.php как `'tpl.msCustomer.layout' => 'ms3_customer_layout'`

---

#### 📊 Примеры кода

**Пример 1: Использование сниппета**
```modx
[[!msCustomer?
    &service=`profile`
    &withLayout=`1`
    &layoutTpl=`ms3_customer_layout`
    &tpl=`ms3_customer_profile`
]]
```

**Пример 2: Роутинг на разные страницы**
```
/account/ → profile (по умолчанию)
/account/?service=addresses → addresses
/account/?service=orders → orders
```

**Пример 3: Детальный просмотр заказа**
```
/account/?service=orders&order_id=123 → OrdersPageService::renderOrderDetails(123)
```

**Пример 4: Fenom syntax для ошибок**
```fenom
{* Проверка наличия ошибки *}
{if $errors.first_name?}
<div class="invalid-feedback">{$errors.first_name}</div>
{/if}

{* Проверка авторизации *}
{if $email_verified}
<span class="verification-badge verified">
    {'ms3_customer_email_verified' | lexicon}
</span>
{/if}
```

**Пример 5: Пагинация в Orders**
```php
$pagination = $this->buildPagination($total, $limit, $offset);
// Возвращает: total, total_pages, current_page, pages[], has_prev, has_next
```

---

#### 🚀 Следующие этапы

**ЭТАП 4** (планируется):
- [ ] API endpoints для обновления профиля (POST /api/v1/customer/profile/update)
- [ ] API endpoints для управления адресами (CRUD)
- [ ] Повторная отправка письма подтверждения email
- [ ] Изменение пароля в профиле
- [ ] Загрузка аватара клиента
- [ ] История операций (security log)

---

### [2025-11-17] ✅ ЭТАП 2: Сохранение адресов доставки клиента - ЗАВЕРШЁН

**Контекст:** Реализован полный функционал сохранения и выбора адресов доставки для авторизованных клиентов с использованием SSR подхода.

---

#### 📋 Итоговая сводка реализованного функционала

**1. Backend API (REST endpoints)**
- ✅ `POST /api/v1/customer/addresses` - создание адреса
- ✅ `GET /api/v1/customer/addresses` - получение списка адресов клиента
- ✅ `PUT /api/v1/customer/addresses/{id}` - обновление адреса
- ✅ `DELETE /api/v1/customer/addresses/{id}` - удаление адреса
- ✅ Контроллер: `CustomerAddressController.php`
- ✅ Роуты: `config/routes/web.php`

**2. Модель данных**
- ✅ Таблица `ms3_customer_addresses` с полями:
  - Адресные данные: country, region, city, street, building, entrance, floor, room, index, metro, text_address
  - Метаданные: name (название адреса), is_default, address_hash (MD5 для дедупликации)
- ✅ xPDO модель `msCustomerAddress`
- ✅ Миграция Phinx для создания таблицы

**3. SSR интеграция (Server-Side Rendering)**
- ✅ Сниппет `ms3_order.php`:
  - Проверка авторизации клиента (`$isCustomerAuth`)
  - Загрузка адресов через `Customer::getAddresses()`
  - Передача данных в шаблон
  - Условная загрузка JavaScript модуля
- ✅ Шаблон `ms3_order.tpl`:
  - Select с сохранёнными адресами (рендерится на сервере)
  - Чекбокс "Сохранить адрес" (только для авторизованных)
  - Fenom условия `{if $isCustomerAuth}`
- ✅ JavaScript модуль `order-addresses.js`:
  - Обработка выбора адреса
  - Автозаполнение полей формы
  - Без API запросов (данные уже в DOM)

**4. Автоматическая регистрация и авторизация**
- ✅ Настройка `ms3_customer_auto_register_on_order` (default: true)
- ✅ Настройка `ms3_customer_auto_login_on_order` (default: true)
- ✅ Автоматическое создание клиента при первом заказе
- ✅ Автологин при создании НОВОГО клиента
- ✅ SECURITY: Автологин ЗАПРЕЩЁН для существующих клиентов (защита от взлома)

**5. Детальное логирование**
- ✅ Логирование всех этапов в `Customer::getOrCreate()` (9 точек)
- ✅ Логирование всех этапов в `Customer::createFromOrderData()` (~20 точек)
- ✅ Все логи на уровне `LOG_LEVEL_ERROR` для мониторинга в продакшене
- ✅ Emoji маркеры для фильтрации: ⏯️ ✅ 🔐 ❌ ⚠️ 🔄 🔍

**6. Лексиконы (i18n)**
- ✅ Русский: `core/components/minishop3/lexicon/ru/customer.inc.php`
- ✅ Английский: `core/components/minishop3/lexicon/en/customer.inc.php`
- ✅ Ключи: `ms3_frontend_saved_addresses`, `ms3_frontend_address_new`, `ms3_frontend_save_address`, и т.д.

---

#### 🎯 Архитектурные решения

**SSR vs CSR:**
- Используется Server-Side Rendering (SSR) вместо Client-Side Rendering
- Адреса рендерятся на сервере через Fenom
- JavaScript отвечает только за UI (выбор адреса, заполнение полей)
- Преимущества: быстрая загрузка, SEO-friendly, работает без JS

**Безопасность:**
- Автологин только для НОВЫХ клиентов
- Существующие клиенты требуют ввода пароля
- MD5 хеширование адресов для предотвращения дубликатов
- Валидация всех входных данных

**Progressive Enhancement:**
1. HTML (SSR) - базовая функциональность работает всегда
2. JavaScript - улучшает UX (автозаполнение)
3. CSS - оформление

---

#### 📂 Файлы изменены/созданы

**Backend:**
- `core/components/minishop3/src/Controllers/Customer/CustomerAddressController.php` ✨ NEW
- `core/components/minishop3/src/Controllers/Customer/Customer.php` 🔄 MODIFIED
- `core/components/minishop3/src/Services/Customer/RegisterService.php` 🔄 MODIFIED
- `core/components/minishop3/config/routes/web.php` 🔄 MODIFIED
- `core/components/minishop3/migrations/20251116000000_create_customer_addresses_table.php` ✨ NEW

**Models:**
- `core/components/minishop3/src/Model/msCustomerAddress.php` ✨ NEW
- `core/components/minishop3/src/Model/mysql/msCustomerAddress.php` ✨ NEW
- `core/components/minishop3/schema/minishop3.mysql.schema.xml` 🔄 MODIFIED

**Frontend:**
- `core/components/minishop3/elements/snippets/ms3_order.php` 🔄 MODIFIED
- `core/components/minishop3/elements/chunks/ms3_order.tpl` 🔄 MODIFIED
- `assets/components/minishop3/js/web/order-addresses.js` ✨ NEW

**Lexicon:**
- `core/components/minishop3/lexicon/ru/customer.inc.php` 🔄 MODIFIED
- `core/components/minishop3/lexicon/en/customer.inc.php` 🔄 MODIFIED

**Settings:**
- `_build/elements/settings.php` 🔄 MODIFIED (добавлены 2 настройки)

---

#### 🐛 Исправленные баги и уязвимости

1. **SECURITY**: Предотвращена уязвимость автологина для существующих клиентов
2. **Fatal Error**: Исправлен отсутствующий namespace `MODX\Revolution\Mail\modMail` в `RegisterService.php`
3. **TypeError**: Исправлена ошибка `getId()` в `Order::error()` (изменено на `getOrCreate()`)

---

#### 📊 Примеры логов

**Успешная регистрация нового клиента:**
```
[ERROR] [Customer::getOrCreate] ⏯️ Starting customer retrieval/creation process. Token: 28b4352f...
[ERROR] [Customer::getOrCreate] Step 1: Searching customer by token...
[ERROR] [Customer::getOrCreate] Step 2: Customer not found by token, proceeding to search/create...
[ERROR] [Customer::getOrCreate] Step 3: Searching customer by email: shura-bura@mail.ru...
[ERROR] [Customer::getOrCreate] Customer not found by email shura-bura@mail.ru
[ERROR] [Customer::getOrCreate] Step 4: Creating new customer via createFromOrderData()...
[ERROR] [Customer::createFromOrderData] Starting customer creation process. Email: shura-bura@mail.ru
[ERROR] [Customer::createFromOrderData] Settings: auto_register=1, auto_login=1, email=shura-bura@mail.ru
[ERROR] [Customer::createFromOrderData] 🔄 Attempting registration via RegisterService
[ERROR] [Customer::createFromOrderData] ✅ Successfully auto-registered customer #6
[ERROR] [Customer::createFromOrderData] 🔐 Auto-logged in customer #6. Session: customer_id=6
[ERROR] [Customer::createFromOrderData] ✅ Process completed successfully. Customer ID: 6, Logged in: YES
[ERROR] [Customer::getOrCreate] ✅ Process completed. Customer ID: 6, Authorized: YES
```

**Существующий клиент (безопасно):**
```
[ERROR] [Customer::getOrCreate] Step 3: Searching customer by email: victim@example.com...
[ERROR] [Customer::getOrCreate] ✅ Found existing customer #4 by email
[ERROR] [Customer::getOrCreate] ⚠️ SECURITY: Existing customer found - auto-login SKIPPED
[ERROR] [Customer::getOrCreate] ✅ Process completed. Customer ID: 4, Authorized: NO ✅
```

---

#### 🚀 Следующие этапы

**ЭТАП 3** (планируется):
- [ ] Сохранение адреса при оформлении заказа (чекбокс "Сохранить адрес")
- [ ] Установка адреса по умолчанию
- [ ] Именование адресов ("Дом", "Работа", и т.д.)
- [ ] Редактирование сохранённых адресов в личном кабинете
- [ ] Удаление адресов

---

### [2025-11-17] 🐛 Исправление: Отсутствующий namespace для modMail

**Контекст:** Fatal error при отправке welcome email - класс `modMail` не найден в `RegisterService.php`.

#### 🐛 Проблема

**Ошибка:**
```
Fatal error: Class "MiniShop3\Services\Customer\modMail" not found
in RegisterService.php:227
```

**Причина:**
В MODX 3 класс `modMail` находится в namespace `MODX\Revolution\Mail\modMail`, но в файле не был добавлен `use` импорт.

#### ✅ Исправление

**Файлы:**
- `core/components/minishop3/src/Services/Customer/RegisterService.php` (строка 8)

**Изменения:**
Добавлен отсутствующий `use` импорт:

```php
namespace MiniShop3\Services\Customer;

use MiniShop3\Controllers\Auth\PasswordAuthProvider;
use MiniShop3\Model\msCustomer;
use MODX\Revolution\modX;
use MODX\Revolution\Mail\modMail;  // ← ДОБАВЛЕНО
```

**Результат:**
Теперь отправка welcome email работает корректно при регистрации нового клиента.

---

### [2025-11-17] 🔒 SECURITY: Предотвращение автологина для существующих клиентов

**Контекст:** Выявлена и предотвращена критическая уязвимость безопасности - автологин при нахождении существующего клиента по email без проверки пароля.

#### 🚨 Уязвимость (предотвращена)

**Сценарий атаки:**
1. Атакующий знает email жертвы: `victim@example.com`
2. Атакующий оформляет заказ с этим email
3. Система находит существующего клиента #123 по email
4. **БЕЗ проверки пароля** автоматически авторизует атакующего
5. Атакующий получает доступ к заказам, адресам, личным данным жертвы

#### ✅ Исправление

**Файлы:**
- `core/components/minishop3/src/Controllers/Customer/Customer.php` (строки 500-503)

**Изменения:**
Удалён опасный автологин для существующих клиентов. Добавлен security лог:

```php
if ($msCustomer) {
    // Обновляем токен существующего клиента
    $oldToken = $msCustomer->get('token');
    $msCustomer->set('token', $this->token);
    $msCustomer->save();

    $this->modx->log(
        modX::LOG_LEVEL_ERROR,
        "[Customer::getOrCreate] ✅ Found existing customer #{$msCustomer->id} by email ({$email}), updated token: {$oldToken} → {$this->token}"
    );

    // SECURITY: НЕ авторизуем существующего клиента без пароля!
    $this->modx->log(
        modX::LOG_LEVEL_ERROR,
        "[Customer::getOrCreate] ⚠️ SECURITY: Existing customer found by email - auto-login SKIPPED (requires password authentication)"
    );
}
```

#### 🔐 Правила безопасности

**Автологин разрешён ТОЛЬКО:**
- ✅ При создании НОВОЙ записи клиента (`createFromOrderData()`)
- ✅ При успешной аутентификации через `LoginService` с паролем

**Автологин ЗАПРЕЩЁН:**
- ❌ При нахождении существующего клиента по email
- ❌ Без проверки пароля или другого фактора аутентификации

#### 📊 Поведение после исправления

**Новый клиент:**
```
[ERROR] [Customer::getOrCreate] Step 4: Creating new customer via createFromOrderData()...
[ERROR] [Customer::createFromOrderData] ✅ Successfully auto-registered customer #5
[ERROR] [Customer::createFromOrderData] 🔐 Auto-logged in customer #5
[ERROR] [Customer::getOrCreate] ✅ Process completed. Customer ID: 5, Authorized: YES ✅
```

**Существующий клиент:**
```
[ERROR] [Customer::getOrCreate] Step 3: Searching customer by email: victim@example.com...
[ERROR] [Customer::getOrCreate] ✅ Found existing customer #4 by email
[ERROR] [Customer::getOrCreate] ⚠️ SECURITY: Existing customer found - auto-login SKIPPED
[ERROR] [Customer::getOrCreate] ✅ Process completed. Customer ID: 4, Authorized: NO ✅
                                                                               ^^^^^ Безопасно!
```

---

### [2025-11-17] 🔧 Изменение уровней логов на LOG_LEVEL_ERROR

**Контекст:** Все логи в методах `Customer::getOrCreate()` и `Customer::createFromOrderData()` изменены с уровней INFO/DEBUG/WARN на LOG_LEVEL_ERROR для упрощения мониторинга в продакшене (логи пишутся в error.log).

#### 🔄 Изменено

**Файлы:**
- `core/components/minishop3/src/Controllers/Customer/Customer.php`

**Методы:**
1. **Customer::getOrCreate()** (строки 424-543):
   - Все 9 log точек изменены с `LOG_LEVEL_INFO` → `LOG_LEVEL_ERROR`
   - Включает логи: поиск по токену, поиск по email, создание нового клиента, финальный результат

2. **Customer::createFromOrderData()** (строки 575-787):
   - Все ~20 log точек изменены с `LOG_LEVEL_INFO/DEBUG/WARN` → `LOG_LEVEL_ERROR`
   - Включает логи: настройки, RegisterService, автологин, fallback метод, финальный результат

#### 📊 Результат

Теперь все логи регистрации и авторизации клиента видны в `error.log` при настройке `log_level` = `LOG_LEVEL_ERROR`:

```
[ERROR] [Customer::getOrCreate] ⏯️ Starting customer retrieval/creation process
[ERROR] [Customer::createFromOrderData] Starting customer creation process. Email: user@example.com
[ERROR] [Customer::createFromOrderData] 🔐 Auto-logged in customer #123
[ERROR] [Customer::getOrCreate] ✅ Process completed. Customer ID: 123, Authorized: YES
```

**Преимущества:**
- ✅ Централизованный мониторинг через error.log
- ✅ Не нужно настраивать отдельные уровни логирования для отладки
- ✅ Все этапы создания клиента видны в одном месте

---

### [2025-11-17] 📝 Логирование: Детальные логи регистрации и авторизации клиента

**Контекст:** Добавлено подробное логирование всех этапов автоматической регистрации и авторизации клиента при оформлении заказа для удобной отладки и мониторинга.

#### ✨ Добавлено

**1. Логирование в Customer::getOrCreate()**

**Файлы:**
- `core/components/minishop3/src/Controllers/Customer/Customer.php` (строки 424-427, 442-454, 458-478, 482-503, 509-511, 533-543)

**Логи:**
```
⏯️ Starting customer retrieval/creation process
Step 1: Searching customer by token...
✅ Found existing customer #123 by token
Step 2: Customer not found by token, proceeding to search/create...
Step 3: Searching customer by email: user@example.com...
✅ Found existing customer #123 by email, updated token
Step 4: Creating new customer via createFromOrderData()...
✅ Process completed. Customer ID: 123, Authorized: YES
```

**2. Логирование в Customer::createFromOrderData()**

**Файлы:**
- `core/components/minishop3/src/Controllers/Customer/Customer.php` (строки 519-522, 525-528, 536-539, 543-546, 551-555, 568-577, 584-587, 594-603, 606-647, 660-713, 716-726)

**Этапы логирования:**

- ✅ Начало процесса создания клиента
- ✅ Проверка наличия email
- ✅ Загрузка системных настроек (auto_register, auto_login)
- ✅ Попытка регистрации через RegisterService
- ✅ Успешная/неуспешная регистрация
- ✅ Автоматический логин (с выводом session данных)
- ✅ Поиск существующего клиента по email
- ✅ Обновление токена существующего клиента
- ✅ Fallback создание (без пароля)
- ✅ Финальный результат процесса

**Примеры логов:**

```
[INFO] Starting customer creation process. Email: user@example.com
[INFO] Settings: auto_register=1, auto_login=1, email=user@example.com
[INFO] 🔄 Attempting registration via RegisterService for user@example.com
[DEBUG] Register data: {"email":"user@example.com","first_name":"Иван","phone":"+79991234567"}
[INFO] ✅ Successfully auto-registered customer #123 (user@example.com) via RegisterService
[INFO] 🔐 Auto-logged in customer #123. Session: customer_id=123, token=abc123def456...
[INFO] ✅ Process completed successfully. Customer ID: 123, Email: user@example.com, Logged in: YES
```

**Логи для ошибок:**

```
[ERROR] ❌ Cannot create customer without email. Order data: {...}
[ERROR] ❌ RegisterService not available in service container
[WARN] ⚠️ RegisterService failed: Email already exists. Trying to find existing customer by email...
[INFO] 🔍 Found existing customer #456 by email user@example.com
[DEBUG] 🔄 Updated customer #456 token: old_token → new_token
[ERROR] ❌ Customer not found by email after RegisterService failure
[ERROR] ❌ Process failed. No customer created for email: user@example.com
```

**Логи для отключенного автологина:**

```
[INFO] ⏭️ Auto-login disabled (ms3_customer_auto_login_on_order=false)
[INFO] ⏭️ Auto-login disabled for existing customer
[INFO] ⏭️ Auto-register disabled (ms3_customer_auto_register_on_order=false)
```

#### 🎯 Уровни логирования

**LOG_LEVEL_INFO** - основные этапы процесса:
- Начало процесса
- Найден клиент (по токену/email)
- Успешная регистрация
- Автологин выполнен
- Финальный результат

**LOG_LEVEL_DEBUG** - детали процесса:
- Данные для регистрации (JSON)
- Шаги поиска
- Обновление токенов
- Промежуточные данные

**LOG_LEVEL_WARN** - предупреждения:
- RegisterService failed (fallback на поиск)

**LOG_LEVEL_ERROR** - критические ошибки:
- Отсутствует email
- RegisterService недоступен
- Клиент не создан
- Events failed

#### 📊 Итоги

**Добавлено логов:**
- Customer::getOrCreate(): 9 точек логирования
- Customer::createFromOrderData(): 20+ точек логирования
- Покрытие всех ветвлений кода (успех/ошибка/fallback)

**Эмодзи маркеры для быстрой фильтрации:**
- ⏯️ - Начало процесса
- 🔄 - Попытка действия
- ✅ - Успех
- 🔐 - Авторизация
- 🔍 - Поиск
- ⚠️ - Предупреждение
- ❌ - Ошибка
- ⏭️ - Пропуск (функция отключена)

**Использование в разработке:**

```bash
# Просмотр всех логов регистрации
tail -f error.log | grep "Customer::"

# Только успешные регистрации
tail -f error.log | grep "✅"

# Только ошибки
tail -f error.log | grep "❌"

# Только автологин
tail -f error.log | grep "🔐"

# Детальная отладка
tail -f error.log | grep -E "(getOrCreate|createFromOrderData)"
```

**Результаты:**
- ✅ Полная прозрачность процесса регистрации/авторизации
- ✅ Удобная отладка проблем с клиентами
- ✅ Мониторинг состояния настроек (auto_register, auto_login)
- ✅ Трассировка токенов через весь flow
- ✅ Визуальная фильтрация по эмодзи

---

### [2025-11-17] 🔐 Авторизация: Автоматический вход клиента после оформления заказа

**Контекст:** Реализован автоматический вход клиента в систему после оформления первого заказа. Теперь гость, оформивший заказ, сразу становится авторизованным клиентом и может видеть сохранённые адреса при следующем заказе.

#### ✨ Добавлено

**1. Системная настройка ms3_customer_auto_login_on_order**

**Файлы:**
- `_build/elements/settings.php` (строка 341-345)

**Настройка:**
```php
'ms3_customer_auto_login_on_order' => [
    'value' => true,  // По умолчанию включено
    'xtype' => 'combo-boolean',
    'area' => 'ms3_customers',
]
```

**Назначение:**
- Автоматически авторизует клиента после создания учётной записи через заказ
- Устанавливает `$_SESSION['ms3']['customer_id']` и `$_SESSION['ms3']['customer_token']`
- Позволяет клиенту сразу видеть функции авторизованного пользователя

**2. Автологин в Customer::createFromOrderData()**

**Файлы:**
- `core/components/minishop3/src/Controllers/Customer/Customer.php` (строки 525, 554-563, 577-587, 610-619)

**Реализация:**
```php
$autoLogin = (bool)$this->modx->getOption('ms3_customer_auto_login_on_order', null, true);

// После успешной регистрации через RegisterService
if ($registerResult['success']) {
    $msCustomer = $registerResult['customer'];

    // Автоматическая авторизация
    if ($autoLogin) {
        $_SESSION['ms3']['customer_id'] = $msCustomer->id;
        $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[Customer::createFromOrderData] Auto-logged in customer #{$msCustomer->id}"
        );
    }
}

// При нахождении существующего клиента по email
if ($msCustomer && $autoLogin) {
    $_SESSION['ms3']['customer_id'] = $msCustomer->id;
    $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');
}

// В fallback методе (создание без пароля)
if ($msCustomer && $autoLogin) {
    $_SESSION['ms3']['customer_id'] = $msCustomer->id;
    $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');
}
```

**Покрытие всех сценариев:**
- ✅ Новый клиент создан через RegisterService (с паролем)
- ✅ Найден существующий клиент по email (уже регистрировался ранее)
- ✅ Создан через fallback метод (без пароля, старый способ)

#### 🎯 Как это работает

**Сценарий 1: Гость оформляет первый заказ**

```
1. Гость заполняет форму заказа:
   - Email: guest@example.com
   - Имя: Иван
   - Город: Москва
   ...

2. Order::submit() вызывает Customer::getOrCreate()
   ↓
3. Customer::createFromOrderData($orderData)
   ↓
4. RegisterService::register([...])
   - Создаёт msCustomer в БД
   - Генерирует случайный пароль: "a3f2b8c1d4e5"
   - Отправляет письмо с паролем
   ↓
5. Автоматическая авторизация (если ms3_customer_auto_login_on_order = true):
   $_SESSION['ms3']['customer_id'] = 123  ✅
   $_SESSION['ms3']['customer_token'] = 'abc...'  ✅
   ↓
6. Редирект на страницу "Спасибо за заказ"
```

**Что видит клиент после заказа:**

```
Страница "Спасибо за заказ":
✅ Клиент авторизован (isCustomerAuth = true)
✅ В шапке сайта может отображаться "Привет, Иван!"
✅ Доступен личный кабинет

Следующий заказ:
✅ Видит select "Сохранённые адреса" (адрес из первого заказа)
✅ Видит checkbox "Сохранить адрес для будущих заказов"
✅ Может заполнить форму одним кликом
```

**Сценарий 2: Клиент уже регистрировался раньше**

```
1. Клиент оформляет заказ с email, который уже есть в БД
   ↓
2. RegisterService::register() возвращает ошибку "Email уже занят"
   ↓
3. Customer::findByEmail($email) находит существующего клиента
   ↓
4. Обновляет токен клиента
   ↓
5. Автоматическая авторизация:
   $_SESSION['ms3']['customer_id'] = существующий ID  ✅
   ↓
6. Клиент видит ВСЕ свои ранее сохранённые адреса
```

#### 📊 Итоги

**Добавлено:**
- 1 системная настройка: `ms3_customer_auto_login_on_order`
- 3 блока автологина в `Customer::createFromOrderData()` (для всех сценариев)
- Логирование действий в MODX log

**Результаты:**
- ✅ Гость автоматически становится авторизованным клиентом после заказа
- ✅ Сразу видит сохранённые адреса при следующем заказе
- ✅ Улучшенный UX - нет необходимости вводить пароль из письма
- ✅ Работает со всеми сценариями создания клиента
- ✅ Гибкая настройка (можно отключить через системную настройку)

**Безопасность:**
- ✅ Авторизация происходит только после успешного создания/нахождения клиента
- ✅ Токен генерируется криптографически стойким способом
- ✅ Логирование всех действий для аудита

**Совместимость:**
- ✅ Обратная совместимость с существующими процессорами Register/Login
- ✅ Не ломает явную регистрацию через `/api/v1/customer/register`
- ✅ Не влияет на logout функционал

**Отключение автологина:**
```
Системные настройки → MiniShop3 → Customers
→ ms3_customer_auto_login_on_order = false
```

---

### [2025-11-17] 🔧 Рефакторинг: SSR подход для сохранённых адресов

**Контекст:** Изменён подход к отображению сохранённых адресов с клиентского рендеринга (JS) на серверный (SSR). Теперь вся логика рендеринга выполняется на сервере, а JavaScript отвечает только за UI взаимодействие.

#### 🔧 Изменено

**1. Сниппет ms3_order.php - добавлена проверка авторизации**

**Файлы:**
- `core/components/minishop3/elements/snippets/ms3_order.php` (строки 40-42, 161-163, 178-189, 287, 295-298)

**Изменения:**
```php
// Проверка авторизации клиента
$isCustomerAuth = !empty($_SESSION['ms3']['customer_id']);
$customerId = $isCustomerAuth ? (int)$_SESSION['ms3']['customer_id'] : 0;

// Загрузка адресов для авторизованных клиентов
if (!empty($includeCustomerAddresses) && $isCustomerAuth && $customerId > 0) {
    $addresses = $ms3->customer->getAddresses($customerId);
}

// Передача флага авторизации в шаблон
$outputData = [
    // ...
    'isCustomerAuth' => $isCustomerAuth,
];

// Подключение JS модуля (SSR подход)
if ($isCustomerAuth && !empty($includeCustomerAddresses)) {
    $assetsUrl = $modx->getOption('ms3_assets_url', null, $modx->getOption('assets_url') . 'components/minishop3/');
    $modx->regClientStartupScript($assetsUrl . 'js/web/order-addresses.js');
}
```

**Преимущества:**
- ✅ Централизованная проверка авторизации на сервере
- ✅ Минимизация дублирования кода (убрана повторная проверка customer_id)
- ✅ Явная передача флага `isCustomerAuth` в шаблон
- ✅ Условное подключение JS модуля только для авторизованных клиентов

**2. Шаблон ms3_order.tpl - Fenom условия вместо JavaScript**

**Файлы:**
- `core/components/minishop3/elements/chunks/ms3_order.tpl` (строки 124-139, 233-243)

**До (❌ Неправильно - клиентский рендеринг):**
```html
<!-- Скрыто по умолчанию, показывается через JS -->
<div id="ms3-saved-addresses-block" style="display: none;">
    <select id="saved_address_id">
        <option value="">Новый адрес</option>
        <!-- Адреса загружаются через fetch API и добавляются в DOM -->
    </select>
</div>

<script>
    // ~200 строк JavaScript для:
    // - Проверки токена
    // - Загрузки адресов через API
    // - Рендеринга option элементов
    // - Показа/скрытия блоков
</script>
```

**После (✅ Правильно - серверный рендеринг):**
```tpl
{* SSR: проверка на сервере, рендеринг в Fenom *}
{if $isCustomerAuth && $addresses && count($addresses) > 0}
<div id="ms3-saved-addresses-block">
    <select id="saved_address_id">
        <option value="">{'ms3_frontend_address_new' | lexicon}</option>
        {foreach $addresses as $address}
        <option value="{$address.id}" data-address='{$address | json_encode}'>
            {$address.name ?: ($address.city ~ ', ' ~ $address.street ~ ', д. ' ~ $address.building)}
        </option>
        {/foreach}
    </select>
</div>
{/if}

{* Чекбокс для всех авторизованных клиентов *}
{if $isCustomerAuth}
<div id="ms3-save-address-block">
    <input type="checkbox" name="save_address" id="save_address" value="1">
    <label for="save_address">{'ms3_frontend_save_address' | lexicon}</label>
</div>
{/if}
```

**3. JavaScript модуль order-addresses.js - только UI логика**

**Файлы:**
- `assets/components/minishop3/js/web/order-addresses.js` (новый файл, ~120 строк)

**Назначение:**
- Обработка события `change` на select (выбор адреса)
- Парсинг данных адреса из `data-address` атрибута
- Заполнение полей формы при выборе адреса
- Очистка полей при выборе "Новый адрес"

**Что НЕ делает:**
- ❌ Не проверяет токен (уже проверено на сервере)
- ❌ Не загружает адреса через API (уже загружены на сервере)
- ❌ Не рендерит option элементы (уже отрендерены в Fenom)
- ❌ Не показывает/скрывает блоки (управляется через Fenom условия)

**Код модуля:**
```javascript
// Минималистичный модуль ~120 строк (было ~200 строк в шаблоне)
(function () {
  'use strict'

  const config = {
    addressSelect: null,
    addressFields: ['country', 'index', 'region', 'city', ...]
  }

  function init() {
    config.addressSelect = document.getElementById('saved_address_id')
    if (!config.addressSelect) return

    config.addressSelect.addEventListener('change', handleAddressChange)
  }

  function handleAddressChange(event) {
    const addressData = event.target.selectedOptions[0].getAttribute('data-address')
    const address = JSON.parse(addressData)
    fillAddressFields(address)
  }

  // ... fillAddressFields(), clearAddressFields()
})()
```

#### 🎯 Архитектурные улучшения

**1. SSR vs CSR (Server-Side vs Client-Side Rendering)**

| Аспект | До (CSR) | После (SSR) |
|--------|----------|-------------|
| Проверка авторизации | JavaScript (`miniShop3.Order.getToken()`) | PHP (`$_SESSION['ms3']['customer_id']`) |
| Загрузка адресов | Fetch API → `/api/v1/customer/addresses` | `$ms3->customer->getAddresses($customerId)` |
| Рендеринг select | JavaScript DOM manipulation | Fenom `{foreach}` |
| Показ/скрытие блоков | JavaScript `style.display = 'block'` | Fenom `{if $isCustomerAuth}` |
| Размер шаблона | ~520 строк (с JS) | ~333 строк (без JS) |
| JavaScript код | ~200 строк в шаблоне | ~120 строк в отдельном модуле |

**2. Разделение ответственности**

**Backend (PHP/Fenom):**
- ✅ Проверка авторизации
- ✅ Загрузка данных из БД
- ✅ Рендеринг HTML структуры
- ✅ Условное отображение блоков
- ✅ Локализация через `| lexicon`

**Frontend (JavaScript):**
- ✅ Обработка пользовательских событий
- ✅ Заполнение/очистка полей формы
- ✅ Анимации и UI эффекты (если нужны)

**3. Преимущества SSR подхода**

- ✅ **SEO:** Адреса видны в HTML до выполнения JavaScript
- ✅ **Производительность:** Нет дополнительных HTTP запросов к API
- ✅ **Безопасность:** Проверка авторизации на сервере, а не в клиенте
- ✅ **Доступность:** Работает даже при отключённом JavaScript (select отображается)
- ✅ **Простота:** Меньше кода, проще отладка
- ✅ **Кэширование:** HTML кэшируется на CDN/прокси
- ✅ **Читаемость:** Вся бизнес-логика в одном месте (сниппет)

**4. Принцип Progressive Enhancement**

```
1. HTML (базовая функциональность) ← SSR рендеринг адресов
   ↓
2. JavaScript (улучшение UX) ← Автозаполнение полей при выборе
   ↓
3. CSS (визуальное оформление) ← Стили для select/checkbox
```

#### 📊 Итоги

**Удалено кода:**
- ~200 строк JavaScript из шаблона ms3_order.tpl
- 2 функции для загрузки адресов через API (loadSavedAddresses, loadAndFillAddress)
- Логика показа/скрытия блоков через JavaScript

**Добавлено кода:**
- ~10 строк PHP в сниппете (проверка авторизации)
- ~15 строк Fenom условий в шаблоне
- ~120 строк JavaScript в отдельном модуле (только UI логика)

**Результаты:**
- ✅ Соблюдён принцип SSR (рендеринг на сервере)
- ✅ Разделение ответственности (backend vs frontend)
- ✅ Модульная структура JS (assets/components/minishop3/js/web/)
- ✅ Минус 1 HTTP запрос к API при загрузке страницы
- ✅ Условное подключение JS модуля (только для авторизованных)
- ✅ Упрощение отладки и поддержки

**Технический долг:**
- Нет обработки ошибок при парсинге JSON из `data-address`
- Нет graceful degradation если JavaScript отключён
- Можно добавить анимацию при заполнении полей

---

### [2025-11-16] 💾 Клиентская часть: Сохранение адресов доставки (ЭТАП 2)

**Контекст:** Реализован второй этап модуля клиентов - сохранение адресов доставки. Клиенты могут выбирать ранее сохранённые адреса при оформлении заказа или сохранять новые адреса для будущих заказов.

#### ✨ Добавлено

**1. REST API для управления адресами клиентов**

**Файлы:**
- `core/components/minishop3/src/Controllers/Api/Web/CustomerAddressController.php` (новый файл, 250+ строк)
- `core/components/minishop3/config/routes/web.php` (строки 211-246)

**Endpoints:**
- `GET /api/v1/customer/addresses` - получить список адресов клиента
- `GET /api/v1/customer/addresses/{id}` - получить конкретный адрес
- `POST /api/v1/customer/addresses` - создать новый адрес
- `PUT /api/v1/customer/addresses/{id}` - обновить адрес
- `DELETE /api/v1/customer/addresses/{id}` - удалить адрес (soft delete)

**Особенности:**
- Защита через TokenMiddleware (требуется MS3TOKEN)
- Автоматическая генерация имени адреса (город, улица, дом)
- Хеширование адресов (MD5) для предотвращения дубликатов
- Форматирование ответов с полными данными адреса

**2. UI элементы в форме оформления заказа**

**Файлы:**
- `core/components/minishop3/elements/chunks/ms3_order.tpl` (строки 123-132, 225-234, 324-511)

**Элементы:**
- Select dropdown для выбора сохранённых адресов
- Checkbox "Сохранить этот адрес для будущих заказов"
- JavaScript для загрузки адресов через API
- Автозаполнение полей формы при выборе адреса

**Поведение:**
- Элементы отображаются только для авторизованных клиентов (проверка токена)
- Автоматическая загрузка списка адресов при инициализации
- Очистка полей при выборе "Ввести новый адрес"

**3. Лексикон для элементов интерфейса**

**Файлы:**
- `core/components/minishop3/lexicon/ru/default.inc.php` (строки 140-144)
- `core/components/minishop3/lexicon/en/default.inc.php` (строки 140-144)

**Добавленные ключи:**
- `ms3_frontend_saved_addresses` - "Сохранённые адреса"
- `ms3_frontend_address_new` - "Ввести новый адрес"
- `ms3_frontend_saved_addresses_help` - подсказка для select
- `ms3_frontend_save_address` - "Сохранить этот адрес для будущих заказов"
- `ms3_frontend_save_address_help` - подсказка для checkbox

#### 🔧 Изменено

**1. Рефакторинг логики создания клиентов (Customer.php)**

**Файлы:**
- `core/components/minishop3/src/Controllers/Customer/Customer.php` (строки 293-692)

**Проблема:** Метод `getId()` имел скрытую логику создания клиента, что затрудняло понимание кода.

**Решение:**
```php
// Новый метод getOrCreate() - чёткое имя, описывающее поведение
public function getOrCreate(?array $orderData = null): int
{
    // 1. Поиск клиента по токену
    // 2. Поиск клиента по email из данных заказа
    // 3. Создание через RegisterService (если включена автоматическая регистрация)
    // 4. Создание без пароля (fallback для обратной совместимости)
}

// Вспомогательный метод для поиска
protected function findByEmail(string $email): ?msCustomer

// Вспомогательный метод для создания
protected function createFromOrderData(array $orderData): ?msCustomer

// Старый метод getId() теперь deprecated wrapper
/**
 * @deprecated Используйте getOrCreate() вместо этого метода
 */
public function getId(): int
{
    return $this->getOrCreate();
}
```

**Преимущества:**
- ✅ Явное имя метода (`getOrCreate` vs `getId`)
- ✅ Разделение на маленькие методы (Single Responsibility)
- ✅ Подробные PHPDoc комментарии с описанием последовательности
- ✅ Обратная совместимость через deprecated wrapper

**2. Улучшение метода Customer::addAddress()**

**Файлы:**
- `core/components/minishop3/src/Controllers/Customer/Customer.php` (строки 414-473)

**Изменения:**
- Добавлена валидация обязательного поля `customer_id`
- Автоматическая генерация имени адреса из city, street, building
- Генерация MD5 хеша для предотвращения дубликатов
- Проверка существующего адреса по хешу перед созданием
- Логирование ошибок в MODX log

**3. Исправление обработки ошибок в Order.php**

**Файлы:**
- `core/components/minishop3/src/Controllers/Order/Order.php` (строки 1353-1361)

**Проблема:** `TypeError: Order::error(): Argument #1 ($message) must be of type string, null given`

**Решение:**
```php
protected function error(?string $message = '', array $data = [], array $placeholders = []): array
{
    // Защита от null: используем fallback сообщение
    if ($message === null || $message === '') {
        $message = 'ms3_err_unknown';
    }

    return $this->ms3->utils->error($message, $data, $placeholders);
}
```

**4. Добавлен лексикон для неизвестных ошибок**

**Файлы:**
- `core/components/minishop3/lexicon/ru/order.inc.php` (строка 37)
- `core/components/minishop3/lexicon/en/order.inc.php` (строка 37)

**Значения:**
- RU: "Неизвестная ошибка. Попробуйте повторить операцию позже или свяжитесь с администратором."
- EN: "Unknown error. Please try again later or contact the administrator."

**5. Улучшена читаемость Order::submit()**

**Файлы:**
- `core/components/minishop3/src/Controllers/Order/Order.php` (строки 674-692)

**Изменения:**
- Добавлены подробные комментарии о последовательности получения/создания клиента
- Логирование ошибок с указанием возможной причины (отсутствие email)
- Явный вызов `getOrCreate()` вместо скрытого `getId()`

#### 🎯 Архитектурные улучшения

**1. Улучшенная читаемость кода**

**До:**
```php
// Непонятно, что метод может СОЗДАВАТЬ клиента
$customer_id = $this->ms3->customer->getId();
```

**После:**
```php
// Чёткое понимание поведения
$customer_id = $this->ms3->customer->getOrCreate($orderData);
```

**2. Защита от дубликатов адресов**

**Механизм:**
```php
// Генерация хеша из ключевых полей адреса
$hash = md5(mb_strtolower(implode('|', [
    $data['city'] ?? '',
    $data['street'] ?? '',
    $data['building'] ?? '',
    $data['room'] ?? '',
])));

// Проверка существования по customer_id + hash
$isExists = $this->modx->getCount(msCustomerAddress::class, [
    'customer_id' => $customerAddressData['customer_id'],
    'hash' => $addressHash,
]);
```

**3. Условное отображение UI элементов**

**JavaScript проверка токена:**
```javascript
function initSavedAddresses() {
    const token = miniShop3.Order.getToken();
    if (!token) return; // Гость - не показываем элементы

    loadSavedAddresses(token);
}
```

#### 📊 Итоги

**Добавлено кода:**
- CustomerAddressController.php: ~250 строк (CRUD API)
- JavaScript в ms3_order.tpl: ~190 строк (загрузка/выбор адресов)
- HTML в ms3_order.tpl: ~25 строк (select + checkbox)
- **Итого:** ~465 строк

**Рефакторинг:**
- Customer.php: getId() → getOrCreate() + 2 вспомогательных метода
- Customer.php: addAddress() улучшен валидацией и хешированием
- Order.php: error() защищён от null значений

**Результаты:**
- ✅ Клиенты могут сохранять адреса для будущих заказов
- ✅ Автозаполнение формы при выборе сохранённого адреса
- ✅ Защита от дубликатов через MD5 хеширование
- ✅ Улучшена читаемость кода создания клиентов
- ✅ Исправлена критическая ошибка с null в error()

**Следующий этап:**
- ЭТАП 3: История заказов клиента
- Личный кабинет с просмотром заказов
- Управление сохранёнными адресами в ЛК

---

### [2025-11-16] 🏗️ Архитектура: Консолидация миграций и упрощение кодовой базы

**Контекст:** Завершен первый этап работы с клиентской частью компонента. Проведена масштабная очистка и консолидация кодовой базы.

#### ✨ Добавлено

**1. Поле index_type в модели msExtraField**

**Файлы:**
- `core/components/minishop3/src/Model/mysql/msExtraField.php` (строки 27, 98-104)

**Назначение:**
- Управление индексацией дополнительных полей (NONE, INDEX, UNIQUE, FULLTEXT)
- Оптимизация поиска по кастомным полям
- Поддержка различных типов индексов MySQL

**2. Таблица msCustomerToken в базовой миграции**

**Файлы:**
- `core/components/minishop3/migrations/20251020000000_initial_schema.php` (строка 20)

**Изменения:**
- Добавлена модель `msCustomerToken` в массив `$modelClasses`
- Теперь таблица создается при начальной установке компонента
- Поддержка API токенов, refresh токенов, magic links, email verification

#### 🔧 Изменено

**1. Консолидация миграций (9→5 файлов)**

**Удаленные миграции:**
- `20251024000001_add_index_type_to_extra_fields.php` - поле перенесено в модель
- `20251113000000_extend_class_field_for_delivery_payment.php` - поля уже в моделях
- `20251115000000_extend_customers_auth_and_security.php` - поля уже в модели
- `20251115000001_create_customer_tokens_table.php` - таблица добавлена в initial_schema
- `20250116000000_add_gdpr_and_moduser_fields_to_customers.php` - поля уже в модели

**Подход:**
- **Model-First:** Вся схема БД определена в xPDO моделях
- **Single Source of Truth:** Модели = источник истины, миграции только применяют их
- **Упрощение:** Меньше файлов миграций = проще поддержка

**Оставшиеся миграции:**
1. `20251020000000_initial_schema.php` - создание всех таблиц из моделей
2. `20251021000000_seed_page_sections.php` - секции страниц
3. `20251022000000_seed_product_fields.php` - поля товаров
4. `20251023000000_seed_order_statuses.php` - статусы заказов
5. `20251023000001_seed_delivery_and_payment.php` - доставка и оплата

**2. Объединение плагинов (2→1)**

**Файлы:**
- `core/components/minishop3/elements/plugins/minishop3.php` (строки 2-189)
- `_build/elements/plugins.php` (добавлены события OnUserSave, OnBeforeUserFormSave, OnUserRemove)

**Удален:**
- `core/components/minishop3/elements/plugins/msCustomerSync.php`

**Изменения:**
- Вся логика синхронизации msCustomer ↔ modUser перенесена в плагин MiniShop3
- Добавлено 3 новых события: OnUserSave, OnBeforeUserFormSave, OnUserRemove
- Автоматическое создание msCustomer при регистрации modUser
- Синхронизация данных (email, имя, телефон, активность)
- Отвязка msCustomer при удалении modUser (сохраняет историю заказов)

**События плагина MiniShop3:**
- OnMODXInit - загрузка дополнительных полей через ExtraFields
- OnLoadWebDocument - инициализация фронтенда, product fields as [[*resource]] tags
- OnManagerPageBeforeRender - подключение лексикона и JS в админке
- OnUserSave - синхронизация msCustomer ↔ modUser (создание/обновление)
- OnBeforeUserFormSave - синхронизация при изменении профиля
- OnUserRemove - отвязка msCustomer от удалённого modUser

**Системные настройки синхронизации:**
- `ms3_customer_sync_enabled` - включить синхронизацию (по умолчанию false)
- `ms3_customer_sync_delete_with_user` - отвязывать msCustomer при удалении modUser

**3. Перемещение RateLimiter в Services/Customer/**

**Файлы:**
- `core/components/minishop3/src/Services/Customer/RateLimiter.php` (перемещен из Middleware/)
- `core/components/minishop3/src/ServiceRegistry.php` (обновлена регистрация)
- 9 файлов с обновленными импортами (7 процессоров + 1 cron)

**Причина:**
- RateLimiter - это сервис, а не HTTP middleware
- Улучшена организация Services/: Category/, Customer/, Order/, Product/, Vendor/
- Единообразие архитектуры

#### ❌ Удалено

**1. Событие OnHandleRequest из плагина**

**Файлы:**
- `core/components/minishop3/elements/plugins/minishop3.php` (удалены строки 15-31)
- `_build/elements/plugins.php` (удалено событие OnHandleRequest)

**Причина:**
- Устаревший механизм обработки AJAX запросов
- Заменен современной архитектурой: `api.php` → FastRoute → API контроллеры
- ms3_action используется только клиентским JavaScript для роутинга

**2. Метод handleRequest() из класса MiniShop3**

**Файлы:**
- `core/components/minishop3/src/MiniShop3.php` (удалено ~110 строк, метод полностью)

**Удаленная логика:**
- Switch-case обработка 16 действий (customer/token/get, cart/add, order/submit и т.д.)
- Ручная маршрутизация через параметр `ms3_action`
- Legacy код для обработки не-AJAX запросов

**Заменено на:**
- REST API через `api.php` с FastRoute роутером
- Декларативная конфигурация роутов в `config/routes/web.php`
- Централизованная обработка через API контроллеры

**3. Fallback обработка форм без JavaScript**

**Файлы:**
- `core/components/minishop3/elements/plugins/minishop3.php` (удалены строки 52-57)

**Причина:**
- Компонент требует JavaScript для работы (современный стандарт e-commerce)
- Упрощение архитектуры
- Все формы обрабатываются через `ms3.js` → `ApiClient` → `api.php`

#### 🎯 Архитектурные улучшения

**1. Упрощенная маршрутизация запросов**

**До:**
```
HTML форма (ms3_action=cart/add)
  ↓
OnHandleRequest (плагин)
  ↓
handleRequest() (switch-case 16 методов)
  ↓
$this->cart->add()
```

**После:**
```
HTML форма (ms3_action=cart/add) — только для клиентского JS
  ↓
ms3.js (читает ms3_action, маршрутизирует)
  ↓
ApiClient (POST /api/v1/cart/add)
  ↓
api.php → FastRoute → CartController
  ↓
Cart::add()
```

**Преимущества:**
- ✅ Разделение клиентской и серверной логики
- ✅ RESTful API (стандартные HTTP методы)
- ✅ Централизованная валидация и middleware
- ✅ Легко расширять новыми endpoints
- ✅ Меньше legacy кода (~110 строк)

**2. Model-First подход к миграциям**

**Принцип:**
- **Модели** (`src/Model/mysql/*.php`) = единственный источник истины для схемы БД
- **Миграции** (`migrations/*.php`) = только применяют схему из моделей
- **Изменения схемы:** Обновляй модель → запускай initial_schema → таблица создается

**Преимущества:**
- ✅ Нет дублирования схемы (было: XML + миграция + модель)
- ✅ Легче поддерживать (одно место для изменений)
- ✅ xPDO createObjectContainer() читает актуальную схему
- ✅ Меньше конфликтов при обновлениях

**3. Единый плагин для всех событий MODX**

**До:** 2 плагина (minishop3.php + msCustomerSync.php)

**После:** 1 плагин (minishop3.php) с 6 событиями

**Преимущества:**
- ✅ Проще управлять (один файл)
- ✅ Меньше накладных расходов (один экземпляр плагина)
- ✅ Централизованная логика компонента

#### 📊 Итоги

**Удалено кода:**
- ~110 строк (метод handleRequest)
- ~150 строк (плагин msCustomerSync, перенесен в основной)
- ~200 строк (4 миграции, логика перенесена в модели)
- **Итого:** ~460 строк удалено

**Результаты консолидации:**
- Миграций: 10 → 5 файлов (-50%)
- Плагинов: 2 → 1 файл (-50%)
- Legacy кода: ~110 строк удалено из MiniShop3.php
- Архитектура: переход на полноценный REST API

**Следующий этап:**
- Развитие клиентской части (Vue компоненты)
- Расширение REST API endpoints
- Документация для разработчиков

---

**Полная история:** См. [архивные файлы](changelogs/) для детальной информации по предыдущим месяцам.
