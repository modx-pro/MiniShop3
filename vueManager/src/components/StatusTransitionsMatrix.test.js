import { flushPromises, mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import request from '../request.js'
import StatusTransitionsMatrix from './StatusTransitionsMatrix.vue'

vi.mock('../request.js', () => ({
  default: {
    get: vi.fn(),
    put: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@vuetools/useLexicon', () => ({
  useLexicon: () => ({
    _: key => key,
  }),
}))

const statuses = [
  { id: 2, name: 'New', color: '000', active: true, final: false, fixed: false, position: 10 },
  { id: 3, name: 'Paid', color: '000', active: true, final: false, fixed: true, position: 20 },
  { id: 4, name: 'Sent', color: '000', active: true, final: false, fixed: false, position: 30 },
  { id: 5, name: 'Cancelled', color: '000', active: true, final: true, fixed: false, position: 40 },
]

describe('StatusTransitionsMatrix (#785)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    request.get.mockResolvedValue({
      mode: 1,
      invalid: false,
      statuses,
      edges: [
        [2, 3],
        [3, 4],
      ],
      unreachable: [5],
    })
    request.put.mockResolvedValue({
      mode: 1,
      invalid: false,
      statuses,
      edges: [[2, 3]],
      unreachable: [3, 4, 5],
    })
  })

  function mountMatrix() {
    return mount(StatusTransitionsMatrix, {
      global: {
        plugins: [PrimeVue, ToastService],
      },
    })
  }

  it('disables diagonal, final row, and fixed backward cells', async () => {
    const wrapper = mountMatrix()
    await flushPromises()

    const { isDisabled } = wrapper.vm
    const [neu, paid, sent, cancelled] = statuses

    expect(isDisabled(neu, neu)).toBe(true)
    expect(isDisabled(cancelled, neu)).toBe(true)
    expect(isDisabled(paid, neu)).toBe(true)
    expect(isDisabled(paid, sent)).toBe(false)
    expect(isDisabled(neu, paid)).toBe(false)
  })

  it('highlights unreachable statuses when allow-list is on', async () => {
    const wrapper = mountMatrix()
    await flushPromises()

    expect(wrapper.text()).toContain('ms3_status_transitions_unreachable_hint')
    expect(wrapper.findAll('.ms3-status-transitions__unreachable').length).toBeGreaterThan(0)
  })

  it('saves selected edges via PUT', async () => {
    const wrapper = mountMatrix()
    await flushPromises()

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(request.put).toHaveBeenCalledWith('/api/mgr/statuses/transitions', {
      edges: expect.arrayContaining([
        [2, 3],
        [3, 4],
      ]),
    })
  })
})
