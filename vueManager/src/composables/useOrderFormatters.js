import {
  formatDate,
  formatPrice,
  renderField as renderProductField,
} from '../utils/displayFormatters.js'

/**
 * Pure formatters for order UI (no order state).
 * Shared date/price/field helpers live in utils/displayFormatters.js.
 */
export function useOrderFormatters() {
  function formatOptions(options) {
    if (!options) return []

    let parsed = options
    if (typeof options === 'string') {
      try {
        parsed = JSON.parse(options)
      } catch {
        return []
      }
    }

    if (Array.isArray(parsed)) {
      return parsed.map(opt => (typeof opt === 'object' ? `${opt.key}: ${opt.value}` : opt))
    }

    if (parsed && typeof parsed === 'object') {
      return Object.entries(parsed).map(([key, value]) => `${key}: ${value}`)
    }

    return []
  }

  function getFieldWidthClass(field) {
    return `col-${field.width || 6}`
  }

  function getProductLink(data, column) {
    if (!column.link?.condition || !data[column.link.condition]) return null
    return column.link.url.replace(/\{(\w+)\}/g, (match, key) => data[key] ?? '')
  }

  return {
    formatDate,
    formatPrice,
    formatOptions,
    getFieldWidthClass,
    renderProductField,
    getProductLink,
  }
}
