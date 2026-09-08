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

  it('treats null, empty string, and missing sort_order the same (end of list)', () => {
    const sectionsById = {
      1: { id: 1, key: 'nullish', sort_order: null },
      2: { id: 2, key: 'empty', sort_order: '' },
      3: { id: 3, key: 'missing' },
      4: { id: 4, key: 'first', sort_order: 10 },
    }
    const fields = [
      { name: 'a', section: 1 },
      { name: 'b', section: 2 },
      { name: 'c', section: 3 },
      { name: 'd', section: 4 },
    ]

    const result = groupProductDataSections(fields, sectionsById)

    expect(result.map(s => s.key)).toEqual(['first', 'nullish', 'empty', 'missing'])
  })

  it('keeps sort_order 0 as a valid leading position', () => {
    const sectionsById = {
      1: { id: 1, key: 'zero', sort_order: 0 },
      2: { id: 2, key: 'ten', sort_order: 10 },
      3: { id: 3, key: 'unset', sort_order: null },
    }
    const fields = [
      { name: 'a', section: 1 },
      { name: 'b', section: 2 },
      { name: 'c', section: 3 },
    ]

    expect(groupProductDataSections(fields, sectionsById).map(s => s.key)).toEqual([
      'zero',
      'ten',
      'unset',
    ])
  })

  it('breaks ties by numeric id then by string key', () => {
    const sectionsById = {
      5: { id: 5, key: 'b', sort_order: 10 },
      2: { id: 2, key: 'a', sort_order: 10 },
    }
    const fields = [
      { name: 'x', section: 5 },
      { name: 'y', section: 2 },
    ]

    expect(groupProductDataSections(fields, sectionsById).map(s => s.id)).toEqual([2, 5])

    const stringTied = groupProductDataSections(
      [
        { name: 'p', section: 'beta' },
        { name: 'q', section: 'alpha' },
      ],
      {
        beta: { key: 'beta', sort_order: 1 },
        alpha: { key: 'alpha', sort_order: 1 },
      }
    )
    expect(stringTied.map(s => s.key)).toEqual(['alpha', 'beta'])
  })
})
