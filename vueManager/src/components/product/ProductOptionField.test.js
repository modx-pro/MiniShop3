import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'

import request from '../../request.js'
import ProductOptionField from './ProductOptionField.vue'

vi.mock('../../request.js', () => ({
  default: {
    get: vi.fn(),
  },
}))

/**
 * Mirrors PrimeVue InputChips commit rules used by comboOptions:
 * Enter, separator (comma), and blur (when addOnBlur) add the typed token.
 */
const InputChipsStub = defineComponent({
  name: 'InputChips',
  props: {
    modelValue: { type: Array, default: () => [] },
    separator: { type: String, default: null },
    addOnBlur: { type: Boolean, default: false },
    inputId: { type: String, default: '' },
    placeholder: { type: String, default: '' },
  },
  emits: ['update:modelValue', 'add', 'remove', 'keyup'],
  setup(props, { emit }) {
    const draft = ref('')

    function commit() {
      const token = draft.value.trim()
      if (!token) {
        return
      }
      const next = [...(props.modelValue || []), token]
      emit('update:modelValue', next)
      emit('add', { value: token })
      draft.value = ''
    }

    return () =>
      h('div', { class: 'input-chips-stub' }, [
        h('input', {
          class: 'chips-draft',
          id: props.inputId,
          value: draft.value,
          placeholder: props.placeholder,
          onInput: event => {
            let next = event.target.value
            if (props.separator && next.includes(props.separator)) {
              draft.value = next.split(props.separator)[0]
              commit()
              event.target.value = draft.value
              return
            }
            draft.value = next
          },
          onKeydown: event => {
            if (event.key === 'Enter') {
              event.preventDefault()
              commit()
            }
          },
          onBlur: () => {
            if (props.addOnBlur) {
              commit()
            }
          },
          onKeyup: event => emit('keyup', event),
        }),
        h('pre', { class: 'chips-model' }, JSON.stringify(props.modelValue)),
      ])
  },
})

function mountComboOptions(optionOverrides = {}) {
  return mount(ProductOptionField, {
    props: {
      option: {
        key: 'tags',
        type: 'comboOptions',
        caption: 'Tags',
        value: [],
        required: 0,
        ...optionOverrides,
      },
    },
    global: {
      stubs: {
        InputChips: InputChipsStub,
        Checkbox: true,
        DatePicker: true,
        InputNumber: true,
        InputText: true,
        MultiSelect: true,
        Select: true,
        Textarea: true,
      },
    },
  })
}

function hiddenPayload(wrapper) {
  return wrapper.get('input[type="hidden"][name="options-tags"]').element.value
}

describe('ProductOptionField comboOptions (#702)', () => {
  let wrapper

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    vi.clearAllMocks()
  })

  it('adds a chip on Enter and writes it to the hidden JSON input', async () => {
    wrapper = mountComboOptions()
    const input = wrapper.get('input.chips-draft')
    await input.setValue('alpha')
    await input.trigger('keydown', { key: 'Enter' })

    expect(JSON.parse(hiddenPayload(wrapper))).toEqual(['alpha'])
    expect(wrapper.emitted('change')?.at(-1)?.[0]).toEqual({ key: 'tags', value: ['alpha'] })
  })

  it('adds a chip when a comma separator is typed', async () => {
    wrapper = mountComboOptions()
    const input = wrapper.get('input.chips-draft')
    await input.setValue('beta,')

    expect(JSON.parse(hiddenPayload(wrapper))).toEqual(['beta'])
    expect(wrapper.emitted('change')?.at(-1)?.[0]).toEqual({ key: 'tags', value: ['beta'] })
  })

  it('adds a chip on blur when add-on-blur is enabled', async () => {
    wrapper = mountComboOptions()
    const input = wrapper.get('input.chips-draft')
    await input.setValue('gamma')
    await input.trigger('blur')

    expect(JSON.parse(hiddenPayload(wrapper))).toEqual(['gamma'])
    expect(wrapper.emitted('change')?.at(-1)?.[0]).toEqual({ key: 'tags', value: ['gamma'] })
  })

  it('loads suggestion pills on first keyup', async () => {
    request.get.mockResolvedValue({ results: ['red', 'green'] })
    wrapper = mountComboOptions()
    const input = wrapper.get('input.chips-draft')
    await input.setValue('r')
    await input.trigger('keyup')
    await flushPromises()

    expect(request.get).toHaveBeenCalledWith('/api/mgr/options/suggestions', {
      key: 'tags',
      limit: 100,
    })
    expect(wrapper.findAll('button.combo-options-suggestion').map(b => b.text())).toEqual([
      'red',
      'green',
    ])
  })
})
