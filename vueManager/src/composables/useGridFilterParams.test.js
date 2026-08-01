import { describe, expect, it } from 'vitest'

import { useGridFilterParams } from './useGridFilterParams.js'

describe('useGridFilterParams', () => {
  it('prefixes unknown keys with filter_', () => {
    const { addFilterParam } = useGridFilterParams()
    const params = {}

    addFilterParam(params, 'query', 'shoes')

    expect(params).toEqual({ filter_query: 'shoes' })
  })

  it('keeps allowlisted keys without prefix', () => {
    const { setDirectFilterKeys, addFilterParam } = useGridFilterParams()
    setDirectFilterKeys(['status', 'date_start'])
    const params = {}

    addFilterParam(params, 'status', 2)
    addFilterParam(params, 'date_start', '2026-04-01')
    addFilterParam(params, 'query', 'x')

    expect(params).toEqual({
      status: 2,
      date_start: '2026-04-01',
      filter_query: 'x',
    })
  })

  it('replaces direct keys set on subsequent calls', () => {
    const { setDirectFilterKeys, addFilterParam } = useGridFilterParams()
    setDirectFilterKeys(['status'])
    setDirectFilterKeys([])
    const params = {}

    addFilterParam(params, 'status', 1)

    expect(params).toEqual({ filter_status: 1 })
  })

  it('treats null/undefined keys as empty allowlist', () => {
    const { setDirectFilterKeys, addFilterParam } = useGridFilterParams()
    setDirectFilterKeys(null)
    const params = {}

    addFilterParam(params, 'vendor', 9)

    expect(params).toEqual({ filter_vendor: 9 })
  })
})
