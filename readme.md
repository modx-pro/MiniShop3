<p align="center">
  <img src="https://modstore.pro/assets/extras/minishop3/logo-lg.png" alt="MiniShop3" width="400">
</p>

<h1 align="center">MiniShop3</h1>

<p align="center">
  <strong>Современный компонент интернет-магазина для MODX 3</strong>
</p>

<p align="center">
  <a href="https://github.com/modx-pro/MiniShop3/releases"><img src="https://img.shields.io/github/v/release/modx-pro/MiniShop3?include_prereleases" alt="Version"></a>
  <a href="#"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="#"><img src="https://img.shields.io/badge/MODX-3.0%2B-green?logo=modx&logoColor=white" alt="MODX 3.0+"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-brightgreen" alt="License MIT"></a>
</p>

<p align="center">
  <a href="https://docs.modx.pro/components/minishop3/">Документация</a> •
  <a href="https://docs.modx.pro/components/minishop3/quick-start">Быстрый старт</a> •
  <a href="https://github.com/modx-pro/MiniShop3/issues">Сообщить о баге</a> •
  <a href="https://github.com/modx-pro/MiniShop3/releases">Релизы</a>
</p>

---

## ✨ Особенности

- 🚀 **Для MODX 3** — PHP 8.2+, namespaces, PSR-4, миграции Phinx
- 🔌 **REST API** — полноценный API для headless-интеграций
- 🎨 **Vue 3 + PrimeVue** — современный интерфейс админки
- ⚡ **Без jQuery** — нативный JavaScript на фронтенде
- 🔄 **Совместимость с miniShop2** — те же сниппеты, чанки и параметры

## 📋 Требования

| Компонент | Версия |
|-----------|--------|
| MODX Revolution | 3.0.0+ |
| PHP | 8.2+ |
| MySQL | 5.7+ / MariaDB 10.3+ |

### Зависимости

| Пакет | Обязательный | Описание |
|-------|--------------|----------|
| [pdoTools 3.x](https://docs.modx.pro/components/pdotools/) | ✅ | Сниппеты и шаблонизатор Fenom |
| [VueTools](https://docs.modx.pro/components/vuetools/) | ✅ | Vue 3 и PrimeVue для админки |
| [Scheduler](https://docs.modx.pro/components/scheduler/) | ❌ | Фоновые задачи (импорт, уведомления) |

## 🚀 Установка

### Через менеджер пакетов (рекомендуется)

```
Extras → Installer → Download Extras → MiniShop3 → Install
```

> ⚠️ Убедитесь, что **VueTools** установлен до MiniShop3

### Из исходников (для разработчиков)

```bash
# Клонирование
git clone https://github.com/modx-pro/MiniShop3.git
cd MiniShop3

# PHP зависимости
cd core/components/minishop3 && composer install && cd ../../..

# Vue виджеты (Node.js 18+)
cd vueManager && npm install && npm run build && cd ..

# Сборка пакета
php _build/build.php
```

## 📖 Документация

Полная документация доступна на **[docs.modx.pro/components/minishop3](https://docs.modx.pro/components/minishop3/)**

- [Быстрый старт](https://docs.modx.pro/components/minishop3/quick-start) — первоначальная настройка
- [Сниппеты](https://docs.modx.pro/components/minishop3/snippets/) — msProducts, msCart, msOrder и др.
- [REST API](https://docs.modx.pro/components/minishop3/development/api) — интеграция с внешними системами
- [События](https://docs.modx.pro/components/minishop3/development/events) — расширение функциональности

### Каталог товаров (Web API)

Публичные endpoints без токена. В выборку попадают только товары с `published=1`, `deleted=0`, `hidemenu=0` в указанном (или текущем) `context`.

```
GET /assets/components/minishop3/api.php?route=/api/v1/product/get/{id}
GET /assets/components/minishop3/api.php?route=/api/v1/product/list
```

Параметры: `parent` / `category` (только primary parent, без `msCategoryMember`), `limit` (max 100), `offset` / `page`, `sort` + `dir`, `query`, `context`, `include_options`, `include_content`.

Ответ `list`: `{ items, total, limit, offset }`. Цена и вес — через `msOnGetProductPrice` / `msOnGetProductWeight`; поля ответа allowlist’ятся после `msOnGetProductFields`.

Полный справочник REST — на [docs.modx.pro](https://docs.modx.pro/components/minishop3/development/api) (раздел каталога стоит синхронизировать с этим релизом).

### Подтверждение email (Web API)

Ссылка в письме ведёт на `api.php` с путём верификации и параметром `html=1` — в ответ сервер отдаёт **HTTP-редирект** (302) на сайт с признаком `ms3_email_verified=1` либо `ms3_email_verified=0`. URL после успешной проверки задаётся системной настройкой `ms3_email_verification_success_url` (если пусто — `site_url`).

Если открыть тот же URL **без** `html=1` или с `format=json`, ответ будет **JSON** (удобно для API-клиентов; в браузере увидите «сырое» тело).

## 🏗️ Структура проекта

```
MiniShop3/
├── _build/                 # Сборка транспортного пакета
├── assets/components/minishop3/
│   ├── js/web/             # Frontend JavaScript
│   ├── js/mgr/             # Admin ExtJS + Vue
│   └── css/                # Стили
├── core/components/minishop3/
│   ├── elements/           # Сниппеты, чанки, плагины
│   ├── src/                # PHP классы (PSR-4)
│   ├── migrations/         # Phinx миграции
│   └── lexicon/            # Переводы (ru, en)
└── vueManager/             # Vue 3 исходники админки
```

### Слои под `src/Controllers/` (HTTP vs domain facade)

Оба живут в namespace `MiniShop3\Controllers\…`, но это **разные роли**. Не кладите HTTP-парсинг в domain facade и не тащите бизнес-логику корзины/заказа в API-класс.

| Слой | Путь | Роль |
|------|------|------|
| HTTP API | `Controllers/Api/Manager/*`, `Controllers/Api/Web/*` (+ соседние `Controllers/Api/*` на manager routes) | Маршруты FastRoute: request → Response / HttpStatus |
| Domain facade (MS2-style) | `Controllers/Cart`, `Order`, `Customer` | Публичный фасад для `$ms3->cart` / `$ms3->order` / `$ms3->customer` и DI; делегирует в `Services/` |
| Provider plugins | `Controllers/Delivery`, `Payment` | Abstract base для методов доставки/оплаты (не DI-фасады `ms3_*`) |
| Services | `Services/*` | Каноническая бизнес-логика |

Ключевые DI-ключи фасадов (см. также `ServiceRegistry`):

| DI key | Класс | Роль |
|--------|-------|------|
| `ms3_cart` | `MiniShop3\Controllers\Cart\Cart` | Domain facade корзины (не HTTP) |
| `ms3_order` | `MiniShop3\Controllers\Order\Order` | Domain facade заказа (не HTTP) |
| `ms3_customer` | `MiniShop3\Controllers\Customer\Customer` | Domain facade покупателя (не HTTP) |

Переименование namespace (`Domain\` / `Facades\`) — отдельный major с bc-aliases; этот репозиторий пока фиксирует границу документацией и PHPDoc.

## 🤝 Участие в разработке

Мы приветствуем вклад в развитие проекта!

1. Форкните репозиторий
2. Создайте ветку для фичи (`git checkout -b feature/amazing-feature`)
3. Закоммитьте изменения (`git commit -m 'Add amazing feature'`)
4. Запушьте ветку (`git push origin feature/amazing-feature`)
5. Откройте Pull Request

## 📝 Changelog

Смотрите [CHANGELOG.md](CHANGELOG.md) для истории изменений.

## 📄 Лицензия

Распространяется под лицензией MIT. Смотрите [LICENSE](LICENSE) для подробностей.

## 💬 Поддержка

- 🐛 [GitHub Issues](https://github.com/modx-pro/MiniShop3/issues) — баги и предложения
- 📚 [Документация](https://docs.modx.pro/components/minishop3/) — руководства и справочники
- 💬 [Telegram](https://t.me/modx_pro) — сообщество MODX

---

<p align="center">
  Сделано с ❤️ для сообщества MODX
</p>
