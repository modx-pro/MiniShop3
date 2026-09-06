import { describe, expect, it } from 'vitest'

import { formatLocalDateYmd } from './formatLocalDateYmd.js'
import { KEY_VALUE_XTYPE } from './keyValueField.js'
import { REPEATER_XTYPE } from './repeaterField.js'
import {
  DATEFIELD_XTYPE,
  isFullWidthExtraFieldXtype,
  parseDateFieldValue,
  parseStructuredExtraFieldValue,
} from './structuredExtraField.js'

describe('structuredExtraField datefield', () => {
  it('does not treat datefield as full-width layout', () => {
    expect(isFullWidthExtraFieldXtype(DATEFIELD_XTYPE)).toBe(false)
  })

  it('parses YYYY-MM-DD into local calendar Date', () => {
    const parsed = parseDateFieldValue('2026-04-20')
    expect(parsed).toBeInstanceOf(Date)
    expect(parsed.getFullYear()).toBe(2026)
    expect(parsed.getMonth()).toBe(3)
    expect(parsed.getDate()).toBe(20)
  })

  it('serializes Date without UTC ISO shift', () => {
    const localMidnight = new Date(2026, 3, 20, 0, 0, 0)
    expect(formatLocalDateYmd(localMidnight)).toBe('2026-04-20')
    expect(formatLocalDateYmd(localMidnight)).not.toBe(localMidnight.toISOString())
  })

  it('leaves datefield scalar in parseStructuredExtraFieldValue', () => {
    const stored = '2026-04-21'
    expect(parseStructuredExtraFieldValue(DATEFIELD_XTYPE, stored)).toBe(stored)
    expect(parseStructuredExtraFieldValue(DATEFIELD_XTYPE, null)).toBeNull()
  })

  it('still parses repeater and key-value structured values', () => {
    expect(parseStructuredExtraFieldValue(REPEATER_XTYPE, '[]')).toEqual([])
    expect(parseStructuredExtraFieldValue(KEY_VALUE_XTYPE, '{}')).toEqual({})
  })
})
