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
    // MODX загружает лексиконы в window.MODx.lang с полным префиксом
    const fullKey = `ms3_vue_${key}`
    const lexicon = window.MODx?.lang || {}

    // Возвращаем перевод из MODx.lang или fallback или сам ключ
    return lexicon[fullKey] || fallback || key
  }

  return {
    _
  }
}
