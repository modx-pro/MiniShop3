import { describe, expect, it } from 'vitest'

import { groupProductDataSections } from './groupProductDataSections.js'

describe('groupProductDataSections', () => {
  it('orders sections by sort_order even when section ids ascend differently', () => {
    const sectionsById = {
      1: { id: 1, key: 'main', sort_order: 20, label: 'Main' },
      3: { id: 3, key: 'first', sort_order: 10, label: 'First' },
      5: { id: 5, key: 'currency', sort_order: 15, label: 'Currency' },
    }
    const fields = [
      { name: 'article', section: 1 },
      { name: 'price', section: 3 },
      { name: 'currency', section: 5 },
    ]

    const result = groupProductDataSections(fields, sectionsById)

    expect(result.map(s => s.id)).toEqual([3, 5, 1])
    expect(result.map(s => s.key)).toEqual(['first', 'currency', 'main'])
    expect(result.map(s => s.label)).toEqual(['First', 'Currency', 'Main'])
  })

  it('skips hidden sections', () => {
    const sectionsById = {
      1: { id: 1, key: 'main', sort_order: 10, hidden: false },
      2: { id: 2, key: 'hidden', sort_order: 5, hidden: true },
    }
    const fields = [
      { name: 'a', section: 1 },
      { name: 'b', section: 2 },
    ]

    expect(groupProductDataSections(fields, sectionsById).map(s => s.id)).toEqual([1])
  })

  it('keeps fields inside a section in input order', () => {
    const sectionsById = {
      1: { id: 1, sort_order: 10 },
    }
    const fields = [
      { name: 'second', section: 1, sort_order: 20 },
      { name: 'first', section: 1, sort_order: 10 },
    ]

    expect(groupProductDataSections(fields, sectionsById)[0].fields.map(f => f.name)).toEqual([
      'second',
      'first',
    ])
  })

  it('falls back to default section when field.section is missing', () => {
    const result = groupProductDataSections([{ name: 'orphan' }], {})
    expect(result).toHaveLength(1)
    expect(result[0].key).toBe('default')
    expect(result[0].fields[0].name).toBe('orphan')
  })
})
