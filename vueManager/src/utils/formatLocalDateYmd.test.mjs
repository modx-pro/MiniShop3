/**
 * Regression: local YYYY-MM-DD must not use UTC from toISOString (#386).
 *
 * Run: node src/utils/formatLocalDateYmd.test.mjs
 */

import assert from 'node:assert/strict'
import { stdout } from 'node:process'

import { formatLocalDateYmd } from './formatLocalDateYmd.js'

assert.equal(formatLocalDateYmd(null), null)
assert.equal(formatLocalDateYmd(''), null)
assert.equal(formatLocalDateYmd('invalid'), null)

// Local calendar components (month is 0-based)
const localMidnight = new Date(2026, 3, 20, 0, 0, 0)
assert.equal(formatLocalDateYmd(localMidnight), '2026-04-20')

// Early local morning east of UTC: toISOString often yields previous UTC day
const earlyLocalMorning = new Date(2026, 3, 21, 1, 0, 0)
assert.equal(formatLocalDateYmd(earlyLocalMorning), '2026-04-21')

const utcDay = earlyLocalMorning.toISOString().split('T')[0]
const offsetMinutes = earlyLocalMorning.getTimezoneOffset()
if (offsetMinutes < 0 && utcDay !== '2026-04-21') {
  // Negative getTimezoneOffset => local is ahead of UTC (e.g. Asia/Almaty)
  assert.notEqual(
    utcDay,
    formatLocalDateYmd(earlyLocalMorning),
    'toISOString must differ from local YMD east of UTC (documents #386 bug)'
  )
}

assert.equal(
  formatLocalDateYmd('2026-04-20T12:00:00'),
  formatLocalDateYmd(new Date('2026-04-20T12:00:00'))
)

stdout.write('OK formatLocalDateYmd.test.mjs\n')
