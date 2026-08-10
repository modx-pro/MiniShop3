import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'

import { GridColumnEditorType } from '../constants/gridColumnEditorTypes.js'
import { useCategoryProductsInlineEdit } from './useCategoryProductsInlineEdit.js'

function createHarness(overrides = {}) {
  const products = ref([{ id: 11, price: 10, published: true, vendor_id: 1 }])
  const referencePathsByKey = ref({})
  const toast = { add: vi.fn() }
  const request = {
    get: vi.fn(),
    put: vi.fn().mockResolvedValue({ id: 11, price: 25 }),
  }
  const _ = key => key
  let api

  const Host = defineComponent({
    setup() {
      api = useCategoryProductsInlineEdit({
        products,
        referencePathsByKey,
        categoryId: 5,
        nested: false,
        request,
        toast,
        _,
        ...overrides,
      })
      return () => h('div')
    },
  })

  const wrapper = mount(Host)
  return { api, products, request, toast, wrapper }
}

describe('useCategoryProductsInlineEdit', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('normalizes boolean and number values for save', () => {
    const { api, wrapper } = createHarness()

    expect(api.normalizeValueForSave(true, { type: 'boolean' })).toBe(1)
    expect(api.normalizeValueForSave(false, { type: 'boolean' })).toBe(0)
    expect(
      api.normalizeValueForSave('12.5', { editor_type: GridColumnEditorType.NUMBER })
    ).toBe(12.5)
    expect(api.normalizeValueForSave('', { editor_type: GridColumnEditorType.NUMBER })).toBeNull()
    expect(
      api.normalizeValueForSave('x', { editor_type: GridColumnEditorType.NUMBER })
    ).toBeNull()

    wrapper.unmount()
  })

  it('detects unchanged number/boolean/select values', () => {
    const { api, wrapper } = createHarness()

    expect(api.isInlineValueUnchanged(true, 1, { type: 'boolean' })).toBe(true)
    expect(api.isInlineValueUnchanged(false, 1, { type: 'boolean' })).toBe(false)
    expect(
      api.isInlineValueUnchanged('10', 10, { editor_type: GridColumnEditorType.NUMBER })
    ).toBe(true)
    expect(
      api.isInlineValueUnchanged(1, '1', { editor_type: GridColumnEditorType.SELECT })
    ).toBe(true)

    wrapper.unmount()
  })

  it('coerces select initial value to option sample type', async () => {
    const { api, wrapper } = createHarness()
    const product = { id: 11, flag: true }
    const column = {
      name: 'flag',
      editable: true,
      editor_type: GridColumnEditorType.SELECT,
      editor_options: [
        { label: 'Yes', value: 1 },
        { label: 'No', value: 0 },
      ],
    }

    await api.startInlineEdit(product, column)

    expect(api.inlineEditValue.value).toBe(1)
    wrapper.unmount()
  })

  it('skips PUT when value is unchanged', async () => {
    const { api, request, wrapper } = createHarness()
    const product = { id: 11, price: 10 }
    const column = {
      name: 'price',
      editable: true,
      editor_type: GridColumnEditorType.NUMBER,
    }

    await api.startInlineEdit(product, column)
    api.inlineEditValue.value = '10'
    await api.saveInlineEdit(product, column)

    expect(request.put).not.toHaveBeenCalled()
    expect(api.editingCell.value).toBeNull()
    wrapper.unmount()
  })

  it('saves with category scope and merges response into products', async () => {
    const { api, products, request, toast, wrapper } = createHarness()
    const product = products.value[0]
    const column = {
      name: 'price',
      editable: true,
      editor_type: GridColumnEditorType.NUMBER,
    }

    await api.startInlineEdit(product, column)
    api.inlineEditValue.value = '25'
    await api.saveInlineEdit(product, column)

    expect(request.put).toHaveBeenCalledWith('/api/mgr/categories/5/products/11/data', {
      price: 25,
    })
    expect(products.value[0].price).toBe(25)
    expect(toast.add).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success' })
    )
    expect(api.editingCell.value).toBeNull()
    wrapper.unmount()
  })

  it('cancels on Escape without saving', async () => {
    const { api, request, wrapper } = createHarness()
    const product = { id: 11, price: 10 }
    const column = {
      name: 'price',
      editable: true,
      editor_type: GridColumnEditorType.NUMBER,
    }

    await api.startInlineEdit(product, column)
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(api.editingCell.value).toBeNull()
    expect(request.put).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
