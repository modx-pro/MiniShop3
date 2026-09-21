<p align="center">
  <img src="https://modstore.pro/assets/extras/minishop3/logo-lg.png" alt="MiniShop3" width="400">
</p>

<h1 align="center">MiniShop3</h1>

<p align="center">
  <strong>Интернет-магазин для MODX 3</strong>
</p>

<p align="center">
  <a href="https://github.com/modx-pro/MiniShop3/releases"><img src="https://img.shields.io/github/v/release/modx-pro/MiniShop3?include_prereleases" alt="Version"></a>
  <a href="#"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="#"><img src="https://img.shields.io/badge/MODX-3.0%2B-green?logo=modx&logoColor=white" alt="MODX 3.0+"></a>
  <img src="https://img.shields.io/badge/license-MIT-brightgreen" alt="License MIT">
</p>

<p align="center">
  <a href="https://docs.modx.pro/components/minishop3/">Документация</a> •
  <a href="https://docs.modx.pro/components/minishop3/quick-start">Быстрый старт</a> •
  <a href="https://github.com/modx-pro/MiniShop3/issues">Сообщить о баге</a> •
  <a href="https://github.com/modx-pro/MiniShop3/releases">Релизы</a>
</p>

---

## ✨ Особенности

- 🚀 **Для MODX 3:** PHP 8.2+, namespaces, PSR-4, миграции Phinx
- 🔌 **REST API** для headless-интеграций
- 🎨 Админка на Vue 3 и PrimeVue
- ⚡ Фронтенд без jQuery, на нативном JavaScript
- 🔄 Сниппеты, чанки и параметры совместимы с miniShop2

## 📋 Требования

| Компонент | Версия |
|-----------|--------|
| MODX Revolution | 3.0.0+ (CI live-тесты: 3.1.2-pl, 3.2.3-pl, 3.2.4-pl) |
| PHP | 8.2+ |
| MySQL | 5.7+ / MariaDB 10.3+ |

### Зависимости

| Пакет | Обязательный | Описание |
|-------|--------------|----------|
| [pdoTools 3.x](https://docs.modx.pro/components/pdotools/) | ✅ | Сниппеты и шаблонизатор Fenom |
| [VueTools](https://docs.modx.pro/components/vuetools/) | ✅ | Vue 3 и PrimeVue для админки |
| [Scheduler](https://docs.modx.pro/components/scheduler/) | ❌ | Фоновые задачи (импорт, уведомления) |

## 🚀 Установка

### Через менеджер пакетов

```
Extras → Installer → Download Extras → MiniShop3 → Install
```

> ⚠️ VueTools нужно поставить до MiniShop3.

### Из исходников

```bash
# Клонирование
git clone https://github.com/modx-pro/MiniShop3.git
cd MiniShop3

# PHP зависимости
cd core/components/minishop3 && composer install && cd ../../..

# Vue-админка (Node.js 18+ локально, в GitHub Actions 24)
cd vueManager && npm ci && npm run build && cd ..

# Сборка пакета
php _build/build.php
```

## 📖 Документация

Справочник: [docs.modx.pro/components/minishop3](https://docs.modx.pro/components/minishop3/)

- [Быстрый старт](https://docs.modx.pro/components/minishop3/quick-start): первоначальная настройка
- [Сниппеты](https://docs.modx.pro/components/minishop3/snippets/): msProducts, msCart, msOrder и др.
- [REST API](https://docs.modx.pro/components/minishop3/development/api): интеграция с внешними системами
- [События](https://docs.modx.pro/components/minishop3/development/events): расширение функциональности

### Каталог товаров (Web API)

Публичные endpoints без токена. В выборку попадают только товары с `published=1`, `deleted=0`, `hidemenu=0` в указанном (или текущем) `context`.

```
GET /assets/components/minishop3/api.php?route=/api/v1/product/get/{id}
GET /assets/components/minishop3/api.php?route=/api/v1/product/list
```

Параметры: `parent` / `category` (только primary parent, без `msCategoryMember`), `limit` (max 100), `offset` / `page`, `sort` + `dir`, `query`, `context`, `include_options`, `include_content`.

Ответ `list`: `{ items, total, limit, offset }`. Цена и вес считаются в `msOnGetProductPrice` / `msOnGetProductWeight`. Поля ответа проходят allowlist после `msOnGetProductFields`.

Полный справочник REST: [docs.modx.pro](https://docs.modx.pro/components/minishop3/development/api). Раздел каталога на сайте документации стоит синхронизировать с этим релизом.

### Подтверждение email (Web API)

Ссылка в письме ведёт на `api.php` с путём верификации и параметром `html=1`. Сервер отвечает HTTP-редиректом 302 на сайт с `ms3_email_verified=1` или `ms3_email_verified=0`. URL после успешной проверки берётся из `ms3_email_verification_success_url`. Если настройка пустая, используется `site_url`.

Тот же URL без `html=1` или с `format=json` возвращает JSON. Так удобнее API-клиентам. В браузере будет сырое тело ответа.

## 🏗️ Структура проекта

```
MiniShop3/
├── _build/                          # Сборка транспортного пакета
├── changelogs/                      # Помесячные записи (сводка в CHANGELOG.md)
├── phpstan.neon                     # PHPStan level 5
├── phpstan-baseline.neon            # Известные замечания PHPStan
├── assets/components/minishop3/
│   ├── api.php                      # Вход публичного Web API
│   ├── connector.php                # Вход менеджерского API
│   ├── js/web/                      # Frontend JavaScript
│   ├── js/mgr/                      # ExtJS и собранный Vue (vue-dist)
│   └── css/
├── core/components/minishop3/
│   ├── controllers/                 # Контроллеры менеджера MODX (не src/Controllers)
│   ├── elements/                    # Сниппеты, чанки, плагины
│   ├── config/
│   │   ├── routes/                  # web.php и manager.php (FastRoute)
│   │   ├── ms3.services.example.php # Пример оверрайда сервисов
│   │   ├── ms3.services.d/          # Доп. сервисы аддонов
│   │   └── ms3.routes.d/            # Доп. маршруты аддонов
│   ├── custom/                      # custom/filters, сейчас .gitkeep
│   ├── schema/                      # xPDO-схема
│   ├── scripts/                     # ci-php.sh, phpstan-prepare-deps.sh
│   ├── src/                         # PHP-классы (PSR-4)
│   │   ├── Controllers/             # HTTP API и domain facade
│   │   ├── Middleware/
│   │   ├── Notifications/
│   │   ├── Processors/              # Процессоры MODX (вызов через connector.php)
│   │   ├── Router/
│   │   ├── Services/
│   │   └── ServiceRegistry.php
│   ├── migrations/                  # Phinx
│   ├── lexicon/                     # Переводы (ru, en)
│   └── tests/                       # Smoke и PHPUnit
└── vueManager/                      # Исходники Vue 3 админки (Vite)
```

### Слои под `src/Controllers/`

Каталог `MiniShop3\Controllers\…` совмещает HTTP и domain facade. Это разные роли. HTTP-разбор не кладите в facade, а логику корзины и заказа не кладите в API-класс. Рядом, в `core/components/minishop3/controllers/`, лежат контроллеры страниц менеджера MODX. Регистр каталога другой, это не тот же слой.

| Слой | Путь | Роль |
|------|------|------|
| HTTP API | `Controllers/Api/Manager/*`, `Controllers/Api/Web/*` (+ соседние `Controllers/Api/*` на manager routes) | Маршруты FastRoute: request → Response / HttpStatus |
| Domain facade (MS2-style) | `Controllers/Cart`, `Order`, `Customer` | Публичный фасад для `$ms3->cart` / `$ms3->order` / `$ms3->customer` и DI; делегирует в `Services/` |
| Provider plugins | `Controllers/Delivery`, `Payment` | Abstract base для методов доставки/оплаты (не DI-фасады `ms3_*`) |
| Services | `Services/*` | Каноническая бизнес-логика |

DI-ключи фасадов (см. также `ServiceRegistry`):

| DI key | Класс | Роль |
|--------|-------|------|
| `ms3_cart` | `MiniShop3\Controllers\Cart\Cart` | Domain facade корзины (не HTTP) |
| `ms3_order` | `MiniShop3\Controllers\Order\Order` | Domain facade заказа (не HTTP) |
| `ms3_customer` | `MiniShop3\Controllers\Customer\Customer` | Domain facade покупателя (не HTTP) |

Переименование namespace (`Domain\` / `Facades\`) остаётся на отдельный major с bc-aliases. Сейчас граница зафиксирована документацией и PHPDoc.

## 🤝 Участие в разработке

1. Форкните репозиторий
2. Создайте ветку (`git checkout -b feature/amazing-feature`)
3. Закоммитьте изменения (`git commit -m 'Add amazing feature'`)
4. Запушьте ветку (`git push origin feature/amazing-feature`)
5. Откройте Pull Request

Если удаляете PHP-файл из поставляемого компонента (`core/components/minishop3/` или `assets/components/minishop3/`), добавьте путь в `config/obsolete_package_files.php`. При апгрейде MODX копирует новое дерево и не удаляет файлы, которых больше нет в пакете. Оставшийся processor по-прежнему вызывается через `connector.php`. Подробности: [`.github/CONTRIBUTING.md`](.github/CONTRIBUTING.md).

### 🧪 Тесты PHP

Из `core/components/minishop3` после `composer install`:

| Команда | Что проверяет |
| --- | --- |
| `composer test:smoke` | Скрипты `tests/*Test.php` без ядра MODX |
| `composer test` | PHPUnit Unit + Integration + WebApi на стабах xPDO |
| `composer ci:php` | `php -l` + smoke + `composer test` (job `PHP lint + smoke`) |
| `composer stan:prepare && composer stan` | PHPStan level 5, baseline в `phpstan-baseline.neon` |
| `composer test:modx` | Живое ядро MODX 3.1+ / 3.2 через [modxkit/testbench](https://github.com/modxkit/testbench) |

`composer test` и `ci:php` не поднимают ядро. Для `test:modx` нужны MySQL и переменные `MODX_TESTBENCH_DB_HOST`, `MODX_TESTBENCH_DB_USER`, `MODX_TESTBENCH_DB_PASS`. Подробности: [`core/components/minishop3/tests/Modx/README.md`](core/components/minishop3/tests/Modx/README.md).

Тесты `@group mysql` входят в `composer test`. Job `PHP lint + smoke` поднимает MySQL 8 и задаёт `MS3_TEST_MYSQL_DSN`, `MS3_TEST_MYSQL_USER`, `MS3_TEST_MYSQL_PASSWORD`. Локально без `MS3_TEST_MYSQL_DSN` эта группа пропускается.

CI гоняет live-сьют на MODX 3.1.2-pl, 3.2.3-pl и 3.2.4-pl. Линейка 3.0.x в этом сьюте не проверяется: ядро не поднимается в API-режиме.

### 🧪 Тесты Vue Manager

Из `vueManager` (Node.js 18+ локально, в CI 24):

Job `vueManager lint` запускает:

```bash
npm ci
npm run lint:ci
npm run lint:storefront
npm test
npm run test:smoke
```

`lint:storefront` проверяет `assets/components/minishop3/js/web`. `test:smoke` проверяет экспорт VueTools. `npm run build` в этом job нет: сборка нужна локально, когда пакет собираете из исходников.

## 📝 Changelog

История изменений: [CHANGELOG.md](CHANGELOG.md). Помесячные файлы лежат в [`changelogs/`](changelogs/).

## 📄 Лицензия

Распространяется под лицензией MIT — она объявлена в [`core/components/minishop3/composer.json`](core/components/minishop3/composer.json).

## 💬 Поддержка

- 🐛 [GitHub Issues](https://github.com/modx-pro/MiniShop3/issues): баги и предложения
- 📚 [Документация](https://docs.modx.pro/components/minishop3/): руководства и справочники
- 💬 [Telegram](https://t.me/modx_pro): сообщество MODX

---

<p align="center">
  Сделано с ❤️ для сообщества MODX
</p>
