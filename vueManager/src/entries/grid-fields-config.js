/**
 * Entry point for Grid Fields Config widget (ES Module)
 *
 * Exports initialization function for mounting Vue application
 */

import '../scss/primevue.scss'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import 'primeicons/primeicons.css'

import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'

import GridFieldsConfig from '../components/GridFieldsConfig.vue'

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
        darkModeSelector: 'none'
      }
    }
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
  $el.dataset.vApp = 'true'

  return app
}

/**
 * Listen for custom event from ExtJS panel
 * Mount application when tab is rendered
 */
document.addEventListener('ms3:mountVueGridFieldsConfig', (event) => {
  const targetId = event.detail?.targetId || '#ms3-grid-fields-config-vue-wrapper'
  init(targetId)
})
