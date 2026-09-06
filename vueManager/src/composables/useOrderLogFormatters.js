import { useLexicon } from '@vuetools/useLexicon'

import { useOrderFormatters } from './useOrderFormatters.js'

/**
 * Log entry display helpers (depends on lexicon + price formatting).
 * Pass `formatPrice` from `useOrderFormatters()` in `options` to avoid a second composable call.
 */
export function useOrderLogFormatters(options = {}) {
  const { _ } = useLexicon()
  const formatPrice = options.formatPrice ?? useOrderFormatters().formatPrice

  const ACTION_SEVERITY = {
    status: 'info',
    products: 'success',
    payment: 'warn',
    address: 'secondary',
    field: 'secondary',
  }

  function formatLogAction(action) {
    if (!action) return '—'
    const key = `log_action_${action}`
    const label = _(key)
    return label !== key ? label : action
  }

  function logActionSeverity(action) {
    return ACTION_SEVERITY[action] || 'secondary'
  }

  function formatProductOperation(op) {
    const key = `log_entry_product_op_${op}`
    const label = _(key)
    return label !== key ? label : op
  }

  function formatLogEntryObject(action, entry) {
    if (!entry) return '—'

    switch (action) {
      case 'status':
        if (entry.new_status_name) {
          return entry.new_status_name
        }
        if (entry.status_id) {
          return _('log_entry_status_id').replace('{id}', String(entry.status_id))
        }
        break

      case 'products': {
        const op = formatProductOperation(entry.operation || 'unknown')
        const productName = entry.product_name || ''
        const count = entry.count ? ` (×${entry.count})` : ''
        return `${op}: ${productName}${count}`.trim()
      }

      case 'field': {
        if (entry.fields) {
          const fieldNames = Object.keys(entry.fields)
          return _('log_entry_fields').replace('{fields}', fieldNames.join(', '))
        }
        break
      }

      case 'address': {
        if (entry.fields) {
          const fieldNames = Object.keys(entry.fields)
          return _('log_entry_address').replace('{fields}', fieldNames.join(', '))
        }
        break
      }

      case 'payment': {
        const payOp = formatProductOperation(entry.operation || 'unknown')
        const amount = entry.amount || 0
        return `${payOp}: ${formatPrice(amount)}`
      }

      default: {
        const pairs = Object.entries(entry)
          .filter(([, v]) => v !== null && v !== undefined)
          .map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : v}`)
          .slice(0, 3)
        return pairs.join(', ') || '—'
      }
    }

    return JSON.stringify(entry)
  }

  function resolveEntryPayload(data) {
    if (!data) return null
    if (data.entry_data != null) return data.entry_data
    return data.entry
  }

  function formatLogEntry(data) {
    if (!data) return '—'

    const raw = resolveEntryPayload(data)
    if (raw == null) return '—'

    if (typeof raw === 'string') {
      try {
        const parsed = JSON.parse(raw)
        return formatLogEntryObject(data.action, parsed)
      } catch {
        return raw
      }
    }

    if (typeof raw === 'object') {
      return formatLogEntryObject(data.action, raw)
    }

    return String(raw)
  }

  return {
    formatLogAction,
    logActionSeverity,
    formatLogEntry,
  }
}
