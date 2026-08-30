/* eslint-disable vue/one-component-per-file -- PrimeVue stubs for unit tests */
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'

import request from '../request.js'
import ResourceCategoryTree from './ResourceCategoryTree.vue'

vi.mock('../request.js', () => ({
  default: {
    get: vi.fn(),
  },
}))

const TreeStub = defineComponent({
  name: 'TreeStub',
  props: { value: { type: Array, default: () => [] } },
  setup(props, { slots }) {
    return () =>
      h(
        'div',
        { class: 'tree-stub' },
        (props.value || []).map(node => slots.default?.({ node }))
      )
  },
})

const CheckboxStub = defineComponent({
  name: 'CheckboxStub',
  props: {
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    inputId: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () =>
      h('input', {
        type: 'checkbox',
        class: 'tree-checkbox-stub',
        checked: props.modelValue,
        disabled: props.disabled,
        id: props.inputId,
        onChange: event => emit('update:modelValue', event.target.checked),
      })
  },
})

const ContextMenuStub = defineComponent({
  name: 'ContextMenu',
  setup(_, { expose }) {
    expose({ show: vi.fn() })
    return () => h('div', { class: 'context-menu-stub' })
  },
})

const globalStubs = {
  Tree: TreeStub,
  Checkbox: CheckboxStub,
  ContextMenu: ContextMenuStub,
}

function categoryRow(id, overrides = {}) {
  return {
    id,
    label: `Category ${id}`,
    leaf: true,
    checked: false,
    selectable: true,
    locked: false,
    class_key: 'MiniShop3\\Model\\msCategory',
    published: 1,
    hidemenu: 0,
    ...overrides,
  }
}

function mockCategoryRows(...rows) {
  request.get.mockResolvedValue({ results: rows })
}

function mountTree(options = {}) {
  const {
    modelValue = [],
    lockedIds = [],
    apiUrl = '/api/mgr/product-data/1/categories/tree',
    apiParams = {},
    onUpdateModelValue,
  } = options

  const selected = ref([...modelValue])
  const emitLog = []

  // Close the real v-model loop (emit → props update → watch) so #546 recursion is detectable.
  const Host = defineComponent({
    setup() {
      return () =>
        h(ResourceCategoryTree, {
          modelValue: selected.value,
          apiUrl,
          apiParams,
          lockedIds,
          inputIdPrefix: 'test-cat-',
          'onUpdate:modelValue': value => {
            selected.value = [...value]
            emitLog.push([...value])
            onUpdateModelValue?.(value)
          },
        })
    },
  })

  const wrapper = mount(Host, {
    global: {
      stubs: globalStubs,
    },
  })

  return { wrapper, selected, emitLog }
}

describe('ResourceCategoryTree', () => {
  afterEach(() => {
    vi.clearAllMocks()
  })

  it('keeps nestedDeep in selection after loadRoot when root page omits it (#641)', async () => {
    const parentId = 10
    const nestedDeepId = 999

    mockCategoryRows(categoryRow(parentId, { checked: true, locked: true }))

    const { selected, emitLog } = mountTree({
      modelValue: [parentId, nestedDeepId],
      lockedIds: [parentId],
    })

    await flushPromises()

    expect(selected.value).toEqual(expect.arrayContaining([parentId, nestedDeepId]))
    expect(selected.value).toHaveLength(2)
    for (const ids of emitLog) {
      expect(ids).toContain(nestedDeepId)
    }
  })

  it('removes a visible category from modelValue when unchecked', async () => {
    const removableId = 20

    mockCategoryRows(categoryRow(removableId, { checked: true }))

    const { wrapper, selected } = mountTree({
      modelValue: [removableId],
    })

    await flushPromises()

    const checkbox = wrapper.find(`#test-cat-${removableId}`)
    expect(checkbox.exists()).toBe(true)
    expect(checkbox.element.checked).toBe(true)

    await checkbox.setValue(false)
    await flushPromises()

    expect(selected.value).not.toContain(removableId)
    expect(selected.value).toHaveLength(0)
  })

  it('enforces locked ids and emits when parent omitted them', async () => {
    const lockedId = 10

    mockCategoryRows(categoryRow(lockedId, { checked: true, locked: true }))

    const { selected, emitLog } = mountTree({
      modelValue: [],
      lockedIds: [lockedId],
    })

    await flushPromises()

    expect(selected.value).toEqual([lockedId])
    expect(emitLog.length).toBeGreaterThan(0)
    expect(emitLog.at(-1)).toEqual([lockedId])
  })

  it('does not emit recursively when modelValue already includes locked ids (#546)', async () => {
    const lockedId = 10
    const nestedDeepId = 999

    mockCategoryRows(categoryRow(lockedId, { checked: true, locked: true }))

    const { emitLog } = mountTree({
      modelValue: [lockedId, nestedDeepId],
      lockedIds: [lockedId],
    })

    await flushPromises()

    expect(emitLog.length).toBeLessThanOrEqual(1)
  })

  it('cannot uncheck a locked category', async () => {
    const lockedId = 10

    mockCategoryRows(categoryRow(lockedId, { checked: true, locked: true }))

    const { wrapper, selected } = mountTree({
      modelValue: [lockedId],
      lockedIds: [lockedId],
    })

    await flushPromises()

    const checkbox = wrapper.find(`#test-cat-${lockedId}`)
    expect(checkbox.element.disabled).toBe(true)

    await checkbox.setValue(false)
    await flushPromises()

    expect(selected.value).toEqual([lockedId])
  })
})
