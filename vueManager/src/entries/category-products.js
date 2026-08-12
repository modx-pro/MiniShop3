/**
 * Entry point for Category Products Grid widget (ES Module)
 *
 * Exports initialization function for mounting Vue application
 * in category update page within ExtJS tab
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import Aura from '@primeuix/themes/aura'
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import { createApp } from 'vue'

import CategoryProductsGrid from '../components/CategoryProductsGrid.vue'
import { provideUiGroup } from '../composables/uiGroup.js'
import { injectFormStylesOverride } from '../utils/formStyles.js'

let appInstance = null

/**
 * Creates and configures Vue application
 * @param {number} categoryId - Category ID
 */
function createVueApp(categoryId) {
  const app = createApp(CategoryProductsGrid, {
    categoryId: categoryId,
  })

  const pinia = createPinia()
  app.use(pinia)

  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: 'none',
      },
    },
    locale: getPrimeVueLocale(),
  })

  app.use(ConfirmationService)
  app.use(ToastService)
  provideUiGroup(app, 'category-products')

  return app
}

/**
 * Widget initialization
 * Called when category products tab is activated
 * @param {string} selector - DOM selector for mount point
 * @param {number} categoryId - Category ID
 */
export function init(selector = '#ms3-vue-category-products', categoryId = 0) {
  const $el = document.querySelector(selector)

  if (!$el) {
    console.warn('[CategoryProducts] Mount element not found:', selector)
    return null
  }

  // Already mounted
  if ($el.dataset.vApp === 'true') {
    return appInstance
  }

  if (!categoryId) {
    console.error('[CategoryProducts] categoryId is required')
    return null
  }

  appInstance = createVueApp(categoryId)
  appInstance.mount(selector)
  injectFormStylesOverride()
  $el.dataset.vApp = 'true'

  return appInstance
}

/**
 * Unmount and cleanup
 */
export function destroy() {
  if (appInstance) {
    appInstance.unmount()
    appInstance = null
  }

  const $el = document.querySelector('#ms3-vue-category-products')
  if ($el) {
    $el.dataset.vApp = 'false'
  }
}

/**
 * Check if app is mounted
 */
export function isMounted() {
  const $el = document.querySelector('#ms3-vue-category-products')
  return $el && $el.dataset.vApp === 'true'
}

// Export for global access
window.MS3CategoryProducts = {
  init,
  destroy,
  isMounted,
}
