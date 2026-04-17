import { useOrderFormatters } from './useOrderFormatters.js'

/**
 * Log entry display helpers (depends on lexicon + price formatting).
 * Pass `formatPrice` from `useOrderFormatters()` in `options` to avoid a second composable call.
 */
export function useOrderLogFormatters(options = {}) {
  const formatPrice = options.formatPrice ?? useOrderFormatters().formatPrice

  function capitalizeFirst(str) {
    if (!str) return ''
    return str.charAt(0).toUpperCase() + str.slice(1)
  }

  function formatLogEntryObject(action, entry) {
    if (!entry) return '-'

    switch (action) {
      case 'status':
        if (entry.new_status_name) {
          return entry.new_status_name
        }
        if (entry.status_id) {
          return `Status ID: ${entry.status_id}`
        }
        break

      case 'products': {
        const op = entry.operation || 'unknown'
        const productName = entry.product_name || ''
        const count = entry.count ? ` (×${entry.count})` : ''
        return `${capitalizeFirst(op)}: ${productName}${count}`
      }

      case 'field': {
        if (entry.fields) {
          const fieldNames = Object.keys(entry.fields)
          return `Fields: ${fieldNames.join(', ')}`
        }
        break
      }

      case 'address': {
        if (entry.fields) {
          const fieldNames = Object.keys(entry.fields)
          return `Address: ${fieldNames.join(', ')}`
        }
        break
      }

      case 'payment': {
        const payOp = entry.operation || 'unknown'
        const amount = entry.amount || 0
        return `${capitalizeFirst(payOp)}: ${formatPrice(amount)}`
      }

      default: {
        const pairs = Object.entries(entry)
          .filter(([, v]) => v !== null && v !== undefined)
          .map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : v}`)
          .slice(0, 3)
        return pairs.join(', ') || '-'
      }
    }

    return JSON.stringify(entry)
  }

  function formatLogEntry(data) {
    if (!data || !data.entry) return '-'

    if (typeof data.entry === 'string') {
      try {
        const parsed = JSON.parse(data.entry)
        return formatLogEntryObject(data.action, parsed)
      } catch {
        return data.entry
      }
    }

    if (typeof data.entry === 'object') {
      return formatLogEntryObject(data.action, data.entry)
    }

    return String(data.entry)
  }

  return {
    formatLogEntry,
  }
}
