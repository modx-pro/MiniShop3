# CHANGELOG - История изменений MiniShop3

Этот файл содержит хронологическую историю всех значительных изменений в проекте.

## Навигация

- **Не выпущено:** [Текущая разработка](#не-выпущено-февраль-2026) (ниже)
- **Текущий месяц:** [Февраль 2026](#февраль-2026) (ниже)
- **Предыдущий месяц:** [Январь 2026](changelogs/2026-01.md)
- **Архив по месяцам:**
  - [Январь 2026](changelogs/2026-01.md)
  - [Декабрь 2025](changelogs/2025-12.md)
  - [Ноябрь 2025](changelogs/2025-11.md)
  - [Октябрь 2025](changelogs/2025-10.md)
  - [Архив (2024 и ранее)](changelogs/archive.md)

---

## Февраль 2026

### Не выпущено Февраль 2026

### Локализация PrimeVue (в разработке)

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
