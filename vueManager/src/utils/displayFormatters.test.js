import { describe, expect, it } from 'vitest'

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
    expect(formatDate(null)).toBe('-')
    expect(formatDate('')).toBe('-')
  })

  it('formatDatePattern applies tokens', () => {
    const value = formatDatePattern('2026-07-16T10:05:00', 'dd.MM.yyyy HH:mm')
    expect(value).toMatch(/^16\.07\.2026 \d{2}:\d{2}$/)
  })

  it('formatPrice formats numbers', () => {
    expect(formatPrice(null)).toBe('-')
    expect(formatPrice(1234.5)).toBe('1\u00a0234,5')
  })

  it('formatPriceConfigured applies currency and separators', () => {
    const formatted = formatPriceConfigured(1200, {
      decimals: 2,
      thousands_separator: ' ',
      decimal_separator: ',',
      currency: '₽',
      currency_position: 'after',
    })
    expect(formatted).toBe('1 200,00 ₽')
  })

  it('renderField supports templates and plain fields', () => {
    expect(renderField({ a: 1, b: 2 }, { template: '{a}-{b}' })).toBe('1-2')
    expect(renderField({ name: 'x' }, { name: 'name' })).toBe('x')
    expect(renderField({}, null)).toBe('')
  })

  it('getDisplayName resolves lexicon keys', () => {
    const translate = key => (key === 'ms3_foo' ? 'Foo' : key)
    expect(getDisplayName('ms3_foo', translate)).toBe('Foo')
    expect(getDisplayName('plain', translate)).toBe('plain')
    expect(getDisplayName('', translate)).toBe('')
  })

  it('formatValue handles boolean and number columns', () => {
    const translate = key => (key === 'yes' ? 'Да' : 'Нет')
    expect(formatValue(true, { type: 'boolean' }, translate)).toBe('Да')
    expect(formatValue(false, { type: 'boolean' }, translate)).toBe('Нет')
    expect(formatValue(1000, { format: 'number' }, translate)).toBe(Number(1000).toLocaleString())
    expect(formatValue(null, {}, translate)).toBe('')
  })

  it('normalizeImagePath keeps absolute URLs and adds leading slash', () => {
    expect(normalizeImagePath('')).toBe('')
    expect(normalizeImagePath('/assets/a.png')).toBe('/assets/a.png')
    expect(normalizeImagePath('https://x/a.png')).toBe('https://x/a.png')
    expect(normalizeImagePath('assets/a.png')).toBe('/assets/a.png')
  })
})
