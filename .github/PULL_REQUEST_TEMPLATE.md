## Описание

Краткое описание изменений и их цели.

## Тип изменений

- [ ] Исправление бага (non-breaking change)
- [ ] Новая функциональность (non-breaking change)
- [ ] Breaking change (изменение, ломающее обратную совместимость)
- [ ] Рефакторинг (без изменения функциональности)
- [ ] Документация
- [ ] Другое (опишите):

## Связанные Issues

Closes #(номер issue)

## Как это было протестировано?

Опишите тесты, которые вы провели для проверки изменений.

Локальный CI-гейт (без полной установки MODX/MySQL), PHP lint + vueManager jobs из `.github/workflows/ci.yml`:

```bash
cd core/components/minishop3
composer install
composer ci:php

cd ../../../vueManager
npm ci
npm run lint:ci
```

PHPStan — отдельный job: `composer stan:prepare && composer stan` (pinned MODX/pdoTools в `.phpstan-deps`).

- [ ] Ручное тестирование
- [ ] Автоматические тесты (`composer ci:php`, `npm run lint:ci`, `composer stan` / GitHub Actions CI)
- [ ] Тестирование на разных версиях PHP/MODX

**Конфигурация тестирования:**
- MiniShop3:
- MODX:
- PHP:

## Скриншоты (если применимо)

| До | После |
|:--:|:-----:|
|    |       |

## Чеклист

- [ ] Код соответствует стилю проекта
- [ ] Добавлены/обновлены комментарии в сложных местах
- [ ] Изменения не ломают существующую функциональность
- [ ] Лексиконы добавлены на **двух языках** (ru/en)
- [ ] PHPStan проходит без новых ошибок (`composer stan` / CI job `PHPStan`)
- [ ] ESLint проходит без ошибок (`npm run lint:ci` для Vue)
- [ ] Обновлён CHANGELOG.md (для значимых изменений)

## Дополнительные заметки

Любая дополнительная информация для ревьюеров.
