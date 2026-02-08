/**
 * Entry point for Help Page Vue application
 */

import '../scss/primevue.scss'
import { createApp } from 'vue'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import 'primeicons/primeicons.css'

import HelpPage from '../components/HelpPage.vue'

/**
 * Creates and configures Vue application
 */
function createVueApp() {
  const app = createApp(HelpPage)

  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: 'none',
      },
    },
  })

  return app
}

/**
 * Widget initialization
 */
export function init(selector = '#ms3-vue-help') {
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
  $el.dataset.vApp = 'true'

  return app
}

/**
 * Automatic initialization on DOM ready
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => init())
} else {
  init()
}
