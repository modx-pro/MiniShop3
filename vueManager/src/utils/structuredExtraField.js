import { KEY_VALUE_XTYPE, parseKeyValueModelValue } from './keyValueField.js'
import { parseRepeaterModelValue, REPEATER_XTYPE } from './repeaterField.js'

export const DATEFIELD_XTYPE = 'datefield'

const STRUCTURED_EXTRA_FIELD_PARSERS = {
  [REPEATER_XTYPE]: parseRepeaterModelValue,
  [KEY_VALUE_XTYPE]: parseKeyValueModelValue,
}

const FULL_WIDTH_EXTRA_FIELD_XTYPES = new Set([REPEATER_XTYPE, KEY_VALUE_XTYPE])

/**
 * Parse stored date (YYYY-MM-DD or ISO) into a local Date for DatePicker.
 *
 * @param {string|number|Date|null|undefined} value
 * @returns {Date|null}
 */
export function parseDateFieldValue(value) {
  if (value == null || value === '') {
    return null
  }

  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value
  }

  if (typeof value !== 'string') {
    return null
  }

  // MySQL zero-date / zero-datetime — treat as empty, not a real calendar day.
  if (value.startsWith('0000-00-00')) {
    return null
  }

  const ymd = value.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (ymd) {
    const local = new Date(Number(ymd[1]), Number(ymd[2]) - 1, Number(ymd[3]))
    return Number.isNaN(local.getTime()) ? null : local
  }

  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

export function isFullWidthExtraFieldXtype(xtype) {
  return FULL_WIDTH_EXTRA_FIELD_XTYPES.has(xtype)
}

export function parseStructuredExtraFieldValue(xtype, value) {
  const parser = STRUCTURED_EXTRA_FIELD_PARSERS[xtype]
  return parser ? parser(value) : value
}
