import { flushPromises, mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import { defineComponent, h } from 'vue'

export const settingsLinksRow = {
  id: 7,
  name: 'Many',
  type: 'many',
  type_label: 'Many',
}

/**
 * Mount settings grids with the same PrimeVue plugins as entries/settings.js.
 */
export function mountSettingsPage(render) {
  return mount(
    defineComponent({
      name: 'SettingsPageHost',
      setup: () => () => render(h),
    }),
    {
      attachTo: document.body,
      global: {
        plugins: [PrimeVue, ConfirmationService, ToastService],
      },
    }
  )
}

export function installSettingsGridMocks(http) {
  http.get.mockImplementation(url => {
    const path = String(url)
    if (path.includes('/api/mgr/links/types')) {
      return Promise.resolve({ results: [] })
    }
    if (path.includes('/api/mgr/links')) {
      return Promise.resolve({ results: [settingsLinksRow], total: 1 })
    }
    if (path.includes('/api/mgr/statuses')) {
      return Promise.resolve({
        results: [{ id: 3, name: 'New', color: '000000', active: true }],
        total: 1,
      })
    }
    if (path.includes('/api/mgr/deliveries')) {
      return Promise.resolve({ results: [{ id: 11, name: 'Pickup' }], total: 1 })
    }
    if (path.includes('/api/mgr/payments')) {
      return Promise.resolve({ results: [{ id: 12, name: 'Card' }], total: 1 })
    }
    if (path.includes('/api/mgr/vendors')) {
      return Promise.resolve({ results: [{ id: 13, name: 'Acme' }], total: 1 })
    }
    if (path.includes('/api/mgr/model-fields')) {
      return Promise.resolve({ results: [], sections: [] })
    }
    if (path.includes('/api/mgr/grid-config/')) {
      return Promise.resolve({})
    }
    if (path.includes('/api/mgr/options/types')) {
      return Promise.resolve({ results: [] })
    }
    if (path.includes('/api/mgr/option-groups')) {
      return Promise.resolve({
        results: [{ id: 4, name: 'Group', options_count: 0 }],
        total: 1,
      })
    }
    if (path.includes('/api/mgr/options')) {
      return Promise.resolve({
        results: [{ id: 9, key: 'color', caption: 'Color', type: 'textfield' }],
        total: 1,
      })
    }
    return Promise.resolve({ results: [], total: 0 })
  })
  http.delete.mockResolvedValue({})
}

export function visibleConfirmDialogs() {
  const nodes = document.querySelectorAll('[data-pc-name="confirmdialog"], .p-confirmdialog')
  return [...new Set(nodes)].filter(isConfirmDialogVisible)
}

function isConfirmDialogVisible(el) {
  if (el.getAttribute('aria-hidden') === 'true') {
    return false
  }

  const target = el.classList.contains('p-dialog') ? el : el.querySelector('.p-dialog') || el
  if (target.classList.contains('p-disabled') && target.getAttribute('aria-hidden') === 'true') {
    return false
  }

  const style = window.getComputedStyle(target)
  return style.display !== 'none' && style.visibility !== 'hidden'
}

export async function clickAcceptConfirm() {
  const dialogs = visibleConfirmDialogs()
  if (dialogs.length === 0) {
    throw new Error('ConfirmDialog accept button not found: no visible dialog')
  }

  const accept = dialogs[0].querySelector(
    '.p-confirmdialog-accept-button, [data-pc-name="pcAcceptButton"]'
  )
  if (!accept) {
    throw new Error('ConfirmDialog accept button not found')
  }

  accept.click()
  await flushPromises()
}

export function boundConfirmGroup(wrapper) {
  const dialog = wrapper.findComponent({ name: 'ConfirmDialog' })
  if (!dialog.exists()) {
    throw new Error('ConfirmDialog component not found')
  }
  return dialog.props('group')
}
