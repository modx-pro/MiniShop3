/**
 * Composable для работы с лексиконом MODX
 *
 * Лексиконы передаются из контроллера через ms3.config.lexicon
 * и зависят от языка админки MODX
 */
export function useLexicon() {
  /**
   * Получить перевод по ключу
   * @param {string} key - Ключ лексикона (например, 'section_add')
   * @param {string} fallback - Резервное значение, если перевод не найден
   * @returns {string}
   */
  function _(key, fallback = key) {
    const lexicon = ms3?.config?.lexicon || {}
    return lexicon[key] || fallback
  }

  return {
    _
  }
}
