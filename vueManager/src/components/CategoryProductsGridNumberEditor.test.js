import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

import { flushPromises, mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import InputNumber from 'primevue/inputnumber'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'

import { useCategoryProductsInlineEdit } from '../composables/useCategoryProductsInlineEdit.js'

/**
 * Number editor of the category products grid (#943).
 *
 * Mirrors the `v-else` branch of CategoryProductsGrid.vue and uses the real PrimeVue
 * InputNumber on purpose: the bug was in the order in which the library writes the
 * typed value into v-model relative to the events we save on. A stub would only
 * repeat whatever ordering we assumed.
 */
let active = null

function createEditor() {
  const products = ref([{ id: 11, wholesale_price: 10 }])
  const column = { name: 'wholesale_price', editable: true, editor_type: 'number' }
  const request = {
    get: vi.fn(),
    put: vi.fn().mockResolvedValue({ id: 11, wholesale_price: 25 }),
  }
  const toast = { add: vi.fn() }
  let api

  const Host = defineComponent({
    setup() {
      api = useCategoryProductsInlineEdit({
        products,
        referencePathsByKey: ref({}),
        categoryId: 5,
        nested: false,
        request,
        toast,
        _: key => key,
      })
      return () =>
        api.editingCell.value
          ? h(
              'div',
              {
                class: 'inline-edit-input-wrapper',
                onKeydown: event => {
                  if (event.key === 'Enter') {
                    event.preventDefault()
                    event.target?.blur?.()
                  }
                },
              },
              [
                h(InputNumber, {
                  modelValue: api.inlineEditValue.value,
                  minFractionDigits: 0,
                  maxFractionDigits: 4,
                  'onUpdate:modelValue': value => {
                    api.inlineEditValue.value = value
                  },
                  onBlur: () => api.saveInlineEdit(products.value[0], column),
                }),
              ]
            )
          : h('div', { class: 'idle' })
    },
  })

  // attachTo: blur() only raises an event on the document active element.
  const wrapper = mount(Host, { attachTo: document.body, global: { plugins: [PrimeVue] } })
  active = wrapper
  return { api, wrapper, request, toast, products, column }
}

/**
 * Leave the field the way a person typing leaves it, then dispatch.
 *
 * Focus first — Enter reaches the save path through blur, and blur only fires on the
 * active element. The value goes in after the focus render has settled: InputNumber
 * rewrites the DOM value on every render, so an await between writing and dispatching
 * would wipe it.
 */
async function typeAndDispatch(wrapper, text, dispatch) {
  const input = wrapper.find('input')
  input.element.focus()
  await nextTick()
  input.element.value = text
  await dispatch(input)
  await flushPromises()
}

describe('CategoryProductsGrid number editor', () => {
  afterEach(() => {
    active?.unmount()
    active = null
  })

  it('sends PUT with the typed value on Enter', async () => {
    const { api, wrapper, request, products, column } = createEditor()
    await api.startInlineEdit(products.value[0], column)
    await nextTick()

    await typeAndDispatch(wrapper, '25', input =>
      input.trigger('keydown', { key: 'Enter', code: 'Enter' })
    )

    expect(request.put).toHaveBeenCalledWith('/api/mgr/categories/5/products/11/data', {
      wholesale_price: 25,
    })
  })

  it('sends PUT with the typed value when the field loses focus', async () => {
    const { api, wrapper, request, products, column } = createEditor()
    await api.startInlineEdit(products.value[0], column)
    await nextTick()

    await typeAndDispatch(wrapper, '25', input => input.trigger('blur'))

    expect(request.put).toHaveBeenCalledWith('/api/mgr/categories/5/products/11/data', {
      wholesale_price: 25,
    })
  })

  /**
   * The harness above runs the handler in the bubble phase, like the grid does now, so
   * it cannot notice a return to `.capture`. This guards the markup itself: capturing
   * Enter blurs the field before InputNumber has written the typed value, which is the
   * whole of #943.
   */
  it('keeps the Enter handler of the number editor out of the capture phase', () => {
    const grid = readFileSync(
      join(dirname(fileURLToPath(import.meta.url)), 'CategoryProductsGrid.vue'),
      'utf8'
    )
    const editorStart = grid.lastIndexOf('<div', grid.indexOf('<InputNumber'))
    const numberEditor = grid.slice(editorStart, grid.indexOf('<InputNumber'))

    expect(numberEditor).toContain('@keydown.enter.prevent')
    expect(numberEditor).not.toContain('@keydown.enter.capture')
  })

  it('skips PUT when the value was not changed', async () => {
    const { api, wrapper, request, products, column } = createEditor()
    await api.startInlineEdit(products.value[0], column)
    await nextTick()

    await typeAndDispatch(wrapper, '10', input =>
      input.trigger('keydown', { key: 'Enter', code: 'Enter' })
    )

    expect(request.put).not.toHaveBeenCalled()
    expect(api.editingCell.value).toBeNull()
  })
})
