import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import request from '../request.js'
import {
  boundConfirmGroup,
  clickAcceptConfirm,
  installSettingsGridMocks,
  mountSettingsPage,
  visibleConfirmDialogs,
} from '../test/settingsGridHarness.js'
import DeliveriesGrid from './DeliveriesGrid.vue'
import LinksGrid from './LinksGrid.vue'
import OptionGroupsGrid from './OptionGroupsGrid.vue'
import OptionsGrid from './OptionsGrid.vue'
import PaymentsGrid from './PaymentsGrid.vue'
import StatusesGrid from './StatusesGrid.vue'
import VendorsGrid from './VendorsGrid.vue'

vi.mock('../request.js', () => ({
  default: {
    get: vi.fn(),
    delete: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
  },
}))

vi.mock('./OptionCategoryTree.vue', () => ({
  default: {
    name: 'OptionCategoryTree',
    template: '<div class="option-category-tree-stub" />',
  },
}))

const SETTINGS_GRIDS = [
  { name: 'LinksGrid', component: LinksGrid, group: 'settings-links' },
  { name: 'StatusesGrid', component: StatusesGrid, group: 'settings-statuses' },
  { name: 'DeliveriesGrid', component: DeliveriesGrid, group: 'settings-deliveries' },
  { name: 'PaymentsGrid', component: PaymentsGrid, group: 'settings-payments' },
  { name: 'VendorsGrid', component: VendorsGrid, group: 'settings-vendors' },
  { name: 'OptionsGrid', component: OptionsGrid, group: 'settings-options' },
  { name: 'OptionGroupsGrid', component: OptionGroupsGrid, group: 'settings-option-groups' },
]

const ROW_DELETE_GRIDS = [
  { name: 'LinksGrid', component: LinksGrid, root: '.links-grid', deleteUrl: '/api/mgr/links/7' },
  {
    name: 'StatusesGrid',
    component: StatusesGrid,
    root: '.statuses-grid',
    deleteUrl: '/api/mgr/statuses/3',
  },
  {
    name: 'DeliveriesGrid',
    component: DeliveriesGrid,
    root: '.deliveries-grid',
    deleteUrl: '/api/mgr/deliveries/11',
  },
  {
    name: 'PaymentsGrid',
    component: PaymentsGrid,
    root: '.payments-grid',
    deleteUrl: '/api/mgr/payments/12',
  },
  {
    name: 'VendorsGrid',
    component: VendorsGrid,
    root: '.vendors-grid',
    deleteUrl: '/api/mgr/vendors/13',
  },
]

describe('settings ConfirmDialog groups (#548, #630)', () => {
  let wrapper

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    vi.clearAllMocks()
  })

  beforeEach(() => {
    installSettingsGridMocks(request)
  })

  it('each settings grid binds a unique ConfirmDialog group', async () => {
    const groups = []
    for (const { component, group } of SETTINGS_GRIDS) {
      wrapper = mountSettingsPage(h => h(component))
      await flushPromises()
      const bound = boundConfirmGroup(wrapper)
      expect(bound).toBe(group)
      groups.push(bound)
      wrapper.unmount()
      wrapper = null
    }
    expect(new Set(groups).size).toBe(SETTINGS_GRIDS.length)
  })

  it('opens only one confirm dialog when Options and Groups share a page', async () => {
    wrapper = mountSettingsPage(h =>
      h('div', { class: 'settings-options-page' }, [h(OptionsGrid), h(OptionGroupsGrid)])
    )
    await flushPromises()

    const optionsDelete = wrapper.find('.options-grid-app [title="delete"]')
    expect(optionsDelete.exists()).toBe(true)

    await optionsDelete.trigger('click')
    await flushPromises()

    expect(visibleConfirmDialogs()).toHaveLength(1)
    expect(request.delete).not.toHaveBeenCalled()
  })

  it.each(ROW_DELETE_GRIDS)(
    '$name confirms once via ActionsColumn then calls request.delete',
    async ({ component, root, deleteUrl }) => {
      wrapper = mountSettingsPage(h => h(component))
      await flushPromises()

      const deleteButton = wrapper.find(`${root} .actions-column [title="delete"]`)
      expect(deleteButton.exists()).toBe(true)

      await deleteButton.trigger('click')
      await flushPromises()

      expect(visibleConfirmDialogs()).toHaveLength(1)
      expect(request.delete).not.toHaveBeenCalled()

      await clickAcceptConfirm()

      expect(visibleConfirmDialogs()).toHaveLength(0)
      expect(request.delete).toHaveBeenCalledTimes(1)
      expect(request.delete).toHaveBeenCalledWith(deleteUrl)
    }
  )
})
