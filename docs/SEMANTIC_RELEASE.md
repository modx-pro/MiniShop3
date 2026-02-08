# Semantic Release

Проект использует [semantic-release](https://semantic-release.gitbook.io/) для автоматического управления версиями и релизами.

## Как это работает

1. **Conventional Commits** — коммиты с префиксами определяют тип релиза:
   - `feat:` → minor (1.0.0 → 1.1.0)
   - `fix:` → patch (1.0.0 → 1.0.1)
   - `BREAKING CHANGE:` или `feat!:` → major (1.0.0 → 2.0.0)

2. **При push** в ветки `beta`, `main` или `master` запускается GitHub Actions workflow.

3. **Если есть новые коммиты** с conventional format — создаётся релиз:
   - Обновляется `package.json`
   - Генерируется `RELEASES.md`
   - Создаётся git tag (например `v1.4.0`)
   - Создаётся GitHub Release

## Conventional Commits — префиксы

| Префикс | Описание | semantic-release |
|---------|----------|------------------|
| `feat:` | Новая функциональность | → minor |
| `fix:` | Исправление бага | → patch |
| `docs:` | Документация | не в changelog |
| `style:` | Форматирование | не в changelog |
| `refactor:` | Рефакторинг | → patch* |
| `perf:` | Оптимизация | → patch |
| `test:` | Тесты | не в changelog |
| `build:` | Сборка | не в changelog |
| `ci:` | CI/CD | не в changelog |
| `chore:` | Прочее | не в changelog |
| `BREAKING CHANGE:` | Ломающие изменения | → major |

*refactor по умолчанию не триггерит релиз, но попадает в changelog если есть feat/fix

## Примеры коммитов

```
feat: add notification center
feat(msOrder): add customerFields parameter
fix: correct order validation
fix: исправлен доступ к ms3.config в Vue
refactor: централизация сервисов в ServiceRegistry
chore: update dependencies
docs: update readme
```

Commitlint проверяет формат при каждом коммите (husky hook).

## CHANGELOG и RELEASES

- **RELEASES.md** — основной файл, который semantic-release обновляет при каждом релизе. Содержит историю версий в conventional format.
- **CHANGELOG.md** — навигация, детальные описания по месяцам, ссылка на RELEASES.md для актуальной истории.

## Локальный запуск (dry-run)

```bash
npm run semantic-release -- --dry-run
```

## Конфигурация

- `.releaserc.json` — настройки semantic-release
- `.github/workflows/release.yml` — GitHub Actions workflow

## Примечание

Текущий `CHANGELOG.md` с ручными записями сохранён. semantic-release пишет в `RELEASES.md`. При необходимости можно объединить форматы или перейти полностью на автоматический changelog.
