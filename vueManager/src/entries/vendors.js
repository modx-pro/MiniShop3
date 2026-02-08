/**
 * Entry point for Vendors Grid Vue application (ES Module)
 *
 * Exports initialization function for mounting Vue application
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import Aura from '@primeuix/themes/aura'
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import { createApp } from 'vue'

import VendorsGrid from '../components/VendorsGrid.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'

/**
 * Creates and configures Vue application
 */
function createVueApp() {
  const app = createApp(VendorsGrid)

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

  return app
}

/**
 * Widget initialization
 * Called when vendors tab is activated
 */
export function init(selector = '#ms3-vue-vendors') {
  const $el = document.querySelector(selector)

  if (!$el) {
    return null
  }

  // Check if already mounted
  if ($el.dataset.vApp === 'true') {
    return null
  }

  const app = createVueApp()
  app.mount(selector)
  injectFormStylesOverride()
  $el.dataset.vApp = 'true'

  return app
}

/**
 * Wait for ExtJS to create DOM element
 * Uses MutationObserver to track element appearance
 */
function waitForElement(selector, callback) {
  const element = document.querySelector(selector)

  if (element) {
    callback(element)
    return
  }

  const observer = new MutationObserver(() => {
    const element = document.querySelector(selector)
    if (element) {
      observer.disconnect()
      callback(element)
    }
  })

  observer.observe(document.body, {
    childList: true,
    subtree: true,
  })
}

/**
 * Automatic initialization on DOM load
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    waitForElement('#ms3-vue-vendors', () => init())
  })
} else {
  waitForElement('#ms3-vue-vendors', () => init())
}
