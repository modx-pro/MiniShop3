/**
 * Entry point for Import Products page (ES Module)
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

import ImportProducts from '../components/ImportProducts.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'

let app = null

/**
 * Creates and configures Vue application
 */
function createVueApp() {
  const vueApp = createApp(ImportProducts)

  vueApp.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        prefix: 'p',
        darkModeSelector: '.ms3-dark-mode',
        cssLayer: false,
      },
    },
    locale: getPrimeVueLocale(),
  })

  vueApp.use(ConfirmationService)
  vueApp.use(ToastService)

  return vueApp
}

/**
 * Widget initialization
 */
export function init(selector = '#ms3-vue-import') {
  const $el = document.querySelector(selector)

  if (!$el) {
    console.warn(`[MS3 Import] Target element not found: ${selector}`)
    return null
  }

  if ($el.dataset.vApp === 'true') {
    return null
  }

  // Unmount existing app if any
  if (app) {
    app.unmount()
  }

  app = createVueApp()
  app.mount(selector)
  injectFormStylesOverride()
  $el.dataset.vApp = 'true'

  return app
}

/**
 * Unmount the application
 */
export function unmount() {
  if (app) {
    app.unmount()
    app = null
  }
}

/**
 * Wait for ExtJS to create DOM element
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

// Listen for mount event from ExtJS
document.addEventListener('ms3:mountVueImport', event => {
  const targetId = event.detail?.targetId || '#ms3-vue-import'
  init(targetId)
})

/**
 * Automatic initialization on DOM load
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    waitForElement('#ms3-vue-import', () => init())
  })
} else {
  waitForElement('#ms3-vue-import', () => init())
}

// Export for programmatic usage
export default { init, unmount }
