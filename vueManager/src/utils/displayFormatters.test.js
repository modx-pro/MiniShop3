import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import {
  formatDate,
  formatDatePattern,
  formatPrice,
  formatPriceConfigured,
  formatValue,
  getDisplayName,
  normalizeImagePath,
  renderField,
} from './displayFormatters.js'

describe('displayFormatters', () => {
  it('formatDate returns dash for empty', () => {
    assert.equal(formatDate(null), '-')
    assert.equal(formatDate(''), '-')
  })

  it('formatDatePattern applies tokens', () => {
    const value = formatDatePattern('2026-07-16T10:05:00', 'dd.MM.yyyy HH:mm')
    assert.match(value, /^16\.07\.2026 \d{2}:\d{2}$/)
  })

  it('formatPrice formats numbers', () => {
    assert.equal(formatPrice(null), '-')
    assert.equal(formatPrice(1234.5), '1\u00a0234,5')
  })

  it('formatPriceConfigured applies currency and separators', () => {
    const formatted = formatPriceConfigured(1200, {
      decimals: 2,
      thousands_separator: ' ',
      decimal_separator: ',',
      currency: '₽',
      currency_position: 'after',
    })
    assert.equal(formatted, '1 200,00 ₽')
  })

  it('renderField supports templates and plain fields', () => {
    assert.equal(renderField({ a: 1, b: 2 }, { template: '{a}-{b}' }), '1-2')
    assert.equal(renderField({ name: 'x' }, { name: 'name' }), 'x')
    assert.equal(renderField({}, null), '')
  })

  it('getDisplayName resolves lexicon keys', () => {
    const translate = key => (key === 'ms3_foo' ? 'Foo' : key)
    assert.equal(getDisplayName('ms3_foo', translate), 'Foo')
    assert.equal(getDisplayName('plain', translate), 'plain')
    assert.equal(getDisplayName('', translate), '')
  })

  it('formatValue handles boolean and number columns', () => {
    const translate = key => (key === 'yes' ? 'Да' : 'Нет')
    assert.equal(formatValue(true, { type: 'boolean' }, translate), 'Да')
    assert.equal(formatValue(false, { type: 'boolean' }, translate), 'Нет')
    assert.equal(formatValue(1000, { format: 'number' }, translate), Number(1000).toLocaleString())
    assert.equal(formatValue(null, {}, translate), '')
  })

  it('normalizeImagePath keeps absolute URLs and adds leading slash', () => {
    assert.equal(normalizeImagePath(''), '')
    assert.equal(normalizeImagePath('/assets/a.png'), '/assets/a.png')
    assert.equal(normalizeImagePath('https://x/a.png'), 'https://x/a.png')
    assert.equal(normalizeImagePath('assets/a.png'), '/assets/a.png')
  })
})
