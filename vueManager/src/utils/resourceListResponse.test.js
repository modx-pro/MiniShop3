import { describe, expect, it } from 'vitest'

import { parsePageResponse } from './resourceListResponse.js'

describe('parsePageResponse', () => {
  it('parses array responses', () => {
    expect(parsePageResponse([1, 2])).toEqual({
      items: [1, 2],
      total: 2,
      malformed: false,
    })
  })

  it('parses results + total', () => {
    expect(parsePageResponse({ results: [{ id: 1 }], total: 9 })).toEqual({
      items: [{ id: 1 }],
      total: 9,
      malformed: false,
    })
  })

  it('marks invalid payloads as malformed', () => {
    expect(parsePageResponse(null)).toEqual({ items: [], total: 0, malformed: true })
    expect(parsePageResponse({})).toEqual({ items: [], total: 0, malformed: true })
  })
})
