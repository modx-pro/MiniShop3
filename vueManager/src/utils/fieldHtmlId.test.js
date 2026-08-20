import { describe, expect, it } from 'vitest'

import { fieldHtmlId } from './fieldHtmlId.js'

describe('fieldHtmlId', () => {
  it('uses the default df prefix', () => {
    expect(fieldHtmlId('color')).toBe('df-field-color')
  })

  it('honours an explicit prefix', () => {
    expect(fieldHtmlId('size', 'order')).toBe('order-field-size')
  })

  it('keeps the prefix even when empty', () => {
    expect(fieldHtmlId('x', '')).toBe('-field-x')
  })
})
