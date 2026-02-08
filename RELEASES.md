# Changelog

Все значимые изменения в проекте документируются в этом файле.  
Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/), версии — на [Semantic Versioning](https://semver.org/lang/ru/).

## [1.4.0-beta1](https://github.com/modx-pro/MiniShop3/compare/1.3.0-beta1...1.4.0-beta1) (2026-02-07)

### Features

* Telegram уведомления для менеджеров при смене статуса заказа ([931ed03](https://github.com/modx-pro/MiniShop3/commit/931ed03))
* email уведомления при смене статуса заказа, удаление неиспользуемых процессоров ([c8865b9](https://github.com/modx-pro/MiniShop3/commit/c8865b9))
* автоматическая конвертация ресурса в товар ([40461bd](https://github.com/modx-pro/MiniShop3/commit/40461bd))
* страница "Помощь и поддержка" на Vue 3 + PrimeVue ([be56735](https://github.com/modx-pro/MiniShop3/commit/be56735))
* события msOnProductsLoad и msOnProductPrepare для интеграции внешних пакетов ([b4c1c6c](https://github.com/modx-pro/MiniShop3/commit/b4c1c6c))
* добавлена локализация PrimeVue через @vuetools/usePrimeVueLocale ([d0754f8](https://github.com/modx-pro/MiniShop3/commit/d0754f8))

### Bug Fixes

* исправлены стили вкладки Options, удалён deprecated код ([537da25](https://github.com/modx-pro/MiniShop3/commit/537da25))
* исправлен доступ к ms3.config в Vue компонентах, удалены неиспользуемые ExtJS файлы ([ef8d605](https://github.com/modx-pro/MiniShop3/commit/ef8d605))
* синхронизация XML схемы и PHP моделей xPDO ([ba7ece6](https://github.com/modx-pro/MiniShop3/commit/ba7ece6))
* исправление замечаний после ревью ([56ba012](https://github.com/modx-pro/MiniShop3/commit/56ba012))

### Code Refactoring

* централизация регистрации сервисов в ServiceRegistry ([fbe35a0](https://github.com/modx-pro/MiniShop3/commit/fbe35a0))
* customerFields параметр и логика выбора источника данных ([c670275](https://github.com/modx-pro/MiniShop3/commit/c670275))

## [1.3.0-beta1](https://github.com/modx-pro/MiniShop3/compare/1.2.3-beta1...1.3.0-beta1) (2026-01-30)

### Features

* вложенные вкладки товара (Vue TabView внутри ExtJS) ([57595ac](https://github.com/modx-pro/MiniShop3/commit/57595ac))
* динамическое обновление msOrderTotal при изменении корзины ([e90e0cf](https://github.com/modx-pro/MiniShop3/commit/e90e0cf))
* добавлен тип поля "Выпадающий список" (ms3-combo-select) ([60c78e5](https://github.com/modx-pro/MiniShop3/commit/60c78e5))
* VueTools в зависимостях, автоустановка при отсутствии ([8286d9e](https://github.com/modx-pro/MiniShop3/commit/8286d9e))

### Bug Fixes

* добавлен воркараунд для поддержки msCategoryMember в msProducts ([97fcae1](https://github.com/modx-pro/MiniShop3/commit/97fcae1))
* добавлены подсказки к полям названия способов доставки и оплаты ([430abd9](https://github.com/modx-pro/MiniShop3/commit/430abd9))
* унифицирована авторизация в CustomerAddressController ([85a9e26](https://github.com/modx-pro/MiniShop3/commit/85a9e26))

### Miscellaneous

* style: правки разметки ([2bfd628](https://github.com/modx-pro/MiniShop3/commit/2bfd628))
* chore: PR template, issue templates, PHPStan в gitignore ([aab50a7](https://github.com/modx-pro/MiniShop3/commit/aab50a7), [0192639](https://github.com/modx-pro/MiniShop3/commit/0192639), [2581b5b](https://github.com/modx-pro/MiniShop3/commit/2581b5b))

## [1.2.3-beta1](https://github.com/modx-pro/MiniShop3/compare/1.2.2-beta.1...1.2.3-beta1) (2026-01-24)

### Features

* чекбокс «Показать вложенные» активен по умолчанию ([9da209e](https://github.com/modx-pro/MiniShop3/commit/9da209e))
* добавлена пагинация на вкладках Производители и Связи товаров ([ca38986](https://github.com/modx-pro/MiniShop3/commit/ca38986))
* автоматический пересчёт стоимости заказа при смене доставки/оплаты ([cba1b58](https://github.com/modx-pro/MiniShop3/commit/cba1b58))

### Bug Fixes

* сохранение токена сессии при авто-регистрации клиента ([335aa9e](https://github.com/modx-pro/MiniShop3/commit/335aa9e))
* корректный расчёт стоимости заказа с учётом доставки ([72167fb](https://github.com/modx-pro/MiniShop3/commit/72167fb))
* перевод названия способа оплаты в диалоге редактирования доставки ([b2c0ef3](https://github.com/modx-pro/MiniShop3/commit/b2c0ef3))
* стили выпадающего списка правил валидации ([9b7da29](https://github.com/modx-pro/MiniShop3/commit/9b7da29))
* автоматическое обновление токена при ошибке ms3_err_token_invalid ([4a3ff4b](https://github.com/modx-pro/MiniShop3/commit/4a3ff4b))
* валидация заказа выполняется до создания customer ([5cb8661](https://github.com/modx-pro/MiniShop3/commit/5cb8661))
* улучшены отступы в формах редактирования доставок и оплат ([3b95723](https://github.com/modx-pro/MiniShop3/commit/3b95723))

## [1.2.0-beta1](https://github.com/modx-pro/MiniShop3/compare/1.1.0-beta1...1.2.0-beta1) (2026-01-16)

### Features

* валидация и создание клиента при оформлении заказа из админки ([886ab34](https://github.com/modx-pro/MiniShop3/commit/886ab34))

### Bug Fixes

* кастомные роуты теперь могут переопределять системные ([ae3ef21](https://github.com/modx-pro/MiniShop3/commit/ae3ef21))
* исправлено неправильное использование getObject/getIterator с сортировкой ([36b2a13](https://github.com/modx-pro/MiniShop3/commit/36b2a13))
* автоматическое продление истёкшего токена вместо ошибки ([b107324](https://github.com/modx-pro/MiniShop3/commit/b107324))
* исправлены ошибки миграций при чистой установке ([ef300fe](https://github.com/modx-pro/MiniShop3/commit/ef300fe))
* поле created_at должно иметь default value ([7231ef5](https://github.com/modx-pro/MiniShop3/commit/7231ef5))

### Code Refactoring

* модернизация API генерации миниатюр и улучшение UI выбора товаров ([5c14298](https://github.com/modx-pro/MiniShop3/commit/5c14298))
* вынос inline стилей в централизованный CSS файл ([ceafa1c](https://github.com/modx-pro/MiniShop3/commit/ceafa1c))
