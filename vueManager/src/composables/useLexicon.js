/**
 * Composable for working with MODX lexicon
 *
 * Uses standard MODX mechanism: window.MODx.lang
 * Lexicons are loaded in controller via $modx->lexicon->load('minishop3:vue')
 */
export function useLexicon() {
  /**
   * Get translation by key
   * @param {string} key - Lexicon key WITHOUT prefix (e.g., 'section_add')
   * @param {string} fallback - Fallback value if translation not found
   * @returns {string}
   */
  function _(key, fallback = null) {
    const lexicon = window.MODx?.lang || {}

    if (lexicon[key]) {
      return lexicon[key]
    }

    const fullKey = `ms3_vue_${key}`
    if (lexicon[fullKey]) {
      return lexicon[fullKey]
    }

    return fallback || key
  }

  return {
    _
  }
}
