/* eslint-disable vue/one-component-per-file -- inline harnesses for provide/inject */
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, defineComponent, h } from 'vue'

const add = vi.fn()
const remove = vi.fn()
const removeGroup = vi.fn()
const removeAllGroups = vi.fn()

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({
    add,
    remove,
    removeGroup,
    removeAllGroups,
  }),
}))

const {
  provideUiGroup,
  toUiGroup,
  useGroupedToast,
  useUiGroup,
  withToastGroup,
} = await import('./uiGroup.js')

describe('uiGroup helpers (#539)', () => {
  beforeEach(() => {
    add.mockClear()
    remove.mockClear()
    removeGroup.mockClear()
    removeAllGroups.mockClear()
  })

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

  it('provideUiGroup reaches useUiGroup in the app tree', () => {
    let seen = null
    const Child = defineComponent({
      setup() {
        seen = useUiGroup()
        return () => null
      },
    })
    const app = createApp({
      setup() {
        return () => h(Child)
      },
    })
    provideUiGroup(app, 'category-products')
    const el = document.createElement('div')
    app.mount(el)
    expect(seen).toBe('category-products')
    app.unmount()
  })

  it('useGroupedToast stamps group on add and keeps removeGroup', () => {
    const Comp = defineComponent({
      setup() {
        const toast = useGroupedToast('product-gallery')
        toast.add({ severity: 'info', summary: 'hi' })
        toast.removeGroup('product-gallery')
        return () => null
      },
    })
    mount(Comp)
    expect(add).toHaveBeenCalledWith({
      severity: 'info',
      summary: 'hi',
      group: 'product-gallery',
    })
    expect(removeGroup).toHaveBeenCalledWith('product-gallery')
  })
})
