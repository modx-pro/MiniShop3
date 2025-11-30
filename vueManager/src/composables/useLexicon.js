/**
 * Composable для работы с лексиконом MODX
 *
 * Использует стандартный механизм MODX: window.MODx.lang
 * Лексиконы загружаются в контроллере через $modx->lexicon->load('minishop3:vue')
 */
export function useLexicon() {
  /**
   * Получить перевод по ключу
   * @param {string} key - Ключ лексикона БЕЗ префикса (например, 'section_add')
   * @param {string} fallback - Резервное значение, если перевод не найден
   * @returns {string}
   */
  function _(key, fallback = null) {
    const lexicon = window.MODx?.lang || {}

    // Сначала пробуем найти ключ как есть (без префикса)
    if (lexicon[key]) {
      return lexicon[key]
    }

    // Если не найден, пробуем с префиксом ms3_vue_
    const fullKey = `ms3_vue_${key}`
    if (lexicon[fullKey]) {
      return lexicon[fullKey]
    }

    // Возвращаем fallback или сам ключ
    return fallback || key
  }

  return {
    _
  }
}
