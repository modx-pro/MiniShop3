import { describe, expect, it } from 'vitest'

import { unwrapResponsePayload } from './request.js'

function envelope(overrides = {}) {
  return { success: true, message: '', ...overrides }
}

describe('unwrapResponsePayload', () => {
  it('returns empty object payload instead of envelope', () => {
    expect(unwrapResponsePayload(envelope({ object: {} }))).toEqual({})
  })

  it('returns list payload with empty results', () => {
    expect(unwrapResponsePayload(envelope({ object: { results: [], total: 0 } }))).toEqual({
      results: [],
      total: 0,
    })
  })

  it('returns empty data array instead of envelope', () => {
    expect(unwrapResponsePayload(envelope({ data: [] }))).toEqual([])
  })

  it('returns object array payload', () => {
    expect(unwrapResponsePayload(envelope({ object: [] }))).toEqual([])
  })

  it('prefers object field over data field', () => {
    expect(unwrapResponsePayload({ success: true, object: { a: 1 }, data: { b: 2 } })).toEqual({
      a: 1,
    })
  })

  it('returns non-array data object', () => {
    expect(unwrapResponsePayload({ success: true, data: { import_id: 'abc' } })).toEqual({
      import_id: 'abc',
    })
  })

  it('falls back to envelope when payload fields are null', () => {
    const payload = { success: true, message: 'Deleted', object: null, data: null }
    expect(unwrapResponsePayload(payload)).toBe(payload)
  })

  it('returns envelope when no payload keys', () => {
    const payload = { success: true, message: 'ok' }
    expect(unwrapResponsePayload(payload)).toBe(payload)
  })

  it.each([
    [0, 0],
    [false, false],
    ['', ''],
  ])('returns falsy scalar object payload (%s)', (value, expected) => {
    expect(unwrapResponsePayload({ success: true, object: value })).toBe(expected)
  })

  it('passes through non-object values', () => {
    expect(unwrapResponsePayload(null)).toBe(null)
    expect(unwrapResponsePayload('ok')).toBe('ok')
  })
})
