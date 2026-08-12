import { describe, expect, it } from 'vitest'

import { toUiGroup, withToastGroup } from './uiGroup.js'

describe('uiGroup helpers (#539)', () => {
  it('toUiGroup maps null/empty to undefined for ConfirmDialog strict ===', () => {
    expect(toUiGroup(null)).toBeUndefined()
    expect(toUiGroup('')).toBeUndefined()
    expect(toUiGroup('gallery')).toBe('gallery')
  })

  it('withToastGroup only adds group when set (Toast default null uses ==)', () => {
    expect(withToastGroup({ severity: 'success' }, null)).toEqual({ severity: 'success' })
    expect(withToastGroup({ severity: 'success' }, 'category-options')).toEqual({
      severity: 'success',
      group: 'category-options',
    })
  })
})
