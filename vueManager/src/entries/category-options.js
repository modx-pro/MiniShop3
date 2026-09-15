/**
 * MiniShop3 - Category → Options tab entry point.
 *
 * Vue application for managing option links of a specific category. Replaces legacy
 * ms3-grid-category-option + ms3-window-option-add + ms3-window-copy-category.
 */

import CategoryOptionsTab from '../components/CategoryOptionsTab.vue'
import { provideUiGroup } from '../composables/uiGroup.js'
import { createMs3VueApp } from '../theme/createMs3VueApp.js'
import { injectFormStylesOverride } from '../utils/formStyles.js'

const MOUNT_ID = 'ms3-vue-category-options'

let app = null

function mountApp() {
  const container = document.getElementById(MOUNT_ID)
  if (!container) return false
  if (app) return true

  const categoryId = parseInt(container.dataset.categoryId || '0', 10)
  if (!categoryId) {
    console.warn('[ms3-category-options] No data-category-id on mount container')
    return false
  }

  app = createMs3VueApp(CategoryOptionsTab, { categoryId }, {
    themeOptions: {
      darkModeSelector: '.dark-mode',
      cssLayer: false,
    },
  })
  provideUiGroup(app, 'category-options')

  app.mount(container)
  injectFormStylesOverride()

  return true
}

function unmountApp() {
  if (app) {
    app.unmount()
    app = null
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountApp)
} else {
  mountApp()
}

if (typeof Ext !== 'undefined') {
  Ext.onReady(function () {
    mountApp()
    setInterval(() => {
      const container = document.getElementById(MOUNT_ID)
      if (container && container.offsetParent !== null) {
        mountApp()
      }
    }, 500)
  })
}

export { mountApp, unmountApp }
