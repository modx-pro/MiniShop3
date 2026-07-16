import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { parsePageResponse } from './resourceListResponse.js'

describe('parsePageResponse', () => {
  it('parses array responses', () => {
    assert.deepEqual(parsePageResponse([1, 2]), {
      items: [1, 2],
      total: 2,
      malformed: false,
    })
  })

  it('parses results + total', () => {
    assert.deepEqual(parsePageResponse({ results: [{ id: 1 }], total: 9 }), {
      items: [{ id: 1 }],
      total: 9,
      malformed: false,
    })
  })

  it('marks invalid payloads as malformed', () => {
    assert.deepEqual(parsePageResponse(null), { items: [], total: 0, malformed: true })
    assert.deepEqual(parsePageResponse({}), { items: [], total: 0, malformed: true })
  })
})
