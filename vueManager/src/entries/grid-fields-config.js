/**
 * Entry point for Grid Fields Config widget (ES Module)
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
import { createApp } from 'vue'

import GridFieldsConfig from '../components/GridFieldsConfig.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'

/**
 * Creates and configures Vue application
 */
function createVueApp() {
  const app = createApp(GridFieldsConfig)

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

  return app
}

/**
 * Widget initialization
 * Called when grid configuration page loads
 */
export function init(selector = '#ms3-grid-fields-config-vue-wrapper') {
  const $el = document.querySelector(selector)

  if (!$el) {
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
 * Listen for custom event from ExtJS panel
 * Mount application when tab is rendered
 */
document.addEventListener('ms3:mountVueGridFieldsConfig', event => {
  const targetId = event.detail?.targetId || '#ms3-grid-fields-config-vue-wrapper'
  init(targetId)
})

/**
 * Automatic initialization - wait for element to appear
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    waitForElement('#ms3-grid-fields-config-vue-wrapper', () =>
      init('#ms3-grid-fields-config-vue-wrapper')
    )
  })
} else {
  waitForElement('#ms3-grid-fields-config-vue-wrapper', () =>
    init('#ms3-grid-fields-config-vue-wrapper')
  )
}
