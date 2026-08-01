import { describe, expect, it } from 'vitest'

import { formatLocalDateYmd } from './formatLocalDateYmd.js'

describe('formatLocalDateYmd', () => {
  it('returns null for empty/invalid input', () => {
    expect(formatLocalDateYmd(null)).toBeNull()
    expect(formatLocalDateYmd('')).toBeNull()
    expect(formatLocalDateYmd('invalid')).toBeNull()
  })

  it('uses local calendar components instead of UTC toISOString', () => {
    const localMidnight = new Date(2026, 3, 20, 0, 0, 0)
    expect(formatLocalDateYmd(localMidnight)).toBe('2026-04-20')

    const earlyLocalMorning = new Date(2026, 3, 21, 1, 0, 0)
    expect(formatLocalDateYmd(earlyLocalMorning)).toBe('2026-04-21')

    const utcDay = earlyLocalMorning.toISOString().split('T')[0]
    const offsetMinutes = earlyLocalMorning.getTimezoneOffset()
    if (offsetMinutes < 0 && utcDay !== '2026-04-21') {
      expect(formatLocalDateYmd(earlyLocalMorning)).not.toBe(utcDay)
    }
  })

  it('matches Date and date-string parsing for the same local instant', () => {
    expect(formatLocalDateYmd('2026-04-20T12:00:00')).toBe(
      formatLocalDateYmd(new Date('2026-04-20T12:00:00'))
    )
  })
})
