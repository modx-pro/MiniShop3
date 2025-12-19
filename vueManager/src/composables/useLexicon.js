/**
 * Composable for working with MODX lexicon
 *
 * Uses standard MODX mechanism: window.MODx.lang
 * Lexicons are loaded in controller via $modx->lexicon->load('minishop3:vue')
 */
export function useLexicon() {
  /**
   * Get translation by key with optional placeholder replacement
   * @param {string} key - Lexicon key WITHOUT prefix (e.g., 'section_add')
   * @param {Object|string} placeholdersOrFallback - Placeholders object or fallback string
   * @param {string} fallback - Fallback value if translation not found (when placeholders provided)
   * @returns {string}
   */
  function _(key, placeholdersOrFallback = null, fallback = null) {
    const lexicon = window.MODx?.lang || {}

    let translation = null
    let placeholders = null

    // Determine if second arg is placeholders object or fallback string
    if (typeof placeholdersOrFallback === 'object' && placeholdersOrFallback !== null) {
      placeholders = placeholdersOrFallback
    } else if (typeof placeholdersOrFallback === 'string') {
      fallback = placeholdersOrFallback
    }

    if (lexicon[key]) {
      translation = lexicon[key]
    } else {
      const fullKey = `ms3_vue_${key}`
      if (lexicon[fullKey]) {
        translation = lexicon[fullKey]
      }
    }

    if (!translation) {
      return fallback || key
    }

    // Replace MODX-style placeholders [[+name]]
    if (placeholders) {
      for (const [name, value] of Object.entries(placeholders)) {
        translation = translation.replace(new RegExp(`\\[\\[\\+${name}\\]\\]`, 'g'), value)
      }
    }

    return translation
  }

  return {
    _
  }
}
