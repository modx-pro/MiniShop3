/**
 * Pure formatters for order UI (no order state).
 */
export function useOrderFormatters() {
  function formatDate(dateString) {
    if (!dateString) return '-'
    const date = new Date(dateString)
    return date.toLocaleString('ru-RU', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  function formatPrice(value) {
    if (value === null || value === undefined) return '-'
    return new Intl.NumberFormat('ru-RU', {
      style: 'decimal',
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(value)
  }

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
    if (typeof parsed === 'object') {
      return Object.entries(parsed).map(([key, value]) => `${key}: ${value}`)
    }
    return []
  }

  function getFieldWidthClass(field) {
    const width = field.width || 6
    return `col-${width}`
  }

  function renderProductField(data, column) {
    if (column.template) {
      return column.template.replace(/\{(\w+)\}/g, (match, key) => data[key] ?? '')
    }
    return data[column.name]
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
