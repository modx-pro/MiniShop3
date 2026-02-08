module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [
      2,
      'always',
      [
        'feat', // новая функциональность
        'fix', // исправление бага
        'docs', // документация
        'style', // форматирование (не влияет на код)
        'refactor', // рефакторинг
        'perf', // оптимизация производительности
        'test', // тесты
        'build', // сборка, зависимости
        'ci', // CI/CD
        'chore' // прочее (обновление зависимостей и т.д.)
      ]
    ],
    'header-max-length': [2, 'always', 100],
    'subject-case': [0],
    'subject-empty': [2, 'never'],
    'type-empty': [2, 'never'],
    'scope-empty': [0]
  }
}
