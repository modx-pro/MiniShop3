/**
 * Entry point for Extra Fields Manager widget (ES Module)
 *
 * Exports initialization function for mounting Vue application
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import Aura from '@primeuix/themes/aura'
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import Tooltip from 'primevue/tooltip'
import { createApp } from 'vue'

import VueExtraFieldsManager from '../components/ExtraFieldsManager.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'

/**
 * Creates and configures Vue application
 */
function createVueApp() {
  const app = createApp(VueExtraFieldsManager)

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

  app.directive('tooltip', Tooltip)

  return app
}

/**
 * Widget initialization
 * Called externally when switching to the tab
 */
export function init(selector = '#ms3-vue-extra-fields') {
  const $el = document.querySelector(selector)

  if (!$el) {
    console.warn(`[ExtraFields] Element ${selector} not found`)
    return null
  }

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
 * Listen for mount event from ExtJS
 */
document.addEventListener('ms3:mountVueExtraFields', e => {
  const targetId = e.detail?.targetId || '#ms3-vue-extra-fields'
  init(targetId)
})

/**
 * Automatic initialization - wait for element to appear
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    waitForElement('#ms3-vue-extra-fields', () => init('#ms3-vue-extra-fields'))
  })
} else {
  waitForElement('#ms3-vue-extra-fields', () => init('#ms3-vue-extra-fields'))
}
