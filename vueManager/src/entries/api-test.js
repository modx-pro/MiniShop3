/**
 * Entry point for API Test widget (ES Module)
 *
 * Exports initialization function for mounting Vue application
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import VueApiTest from '../components/ApiTest.vue'
import { createMs3VueApp } from '../theme/createMs3VueApp.js'
import { injectFormStylesOverride } from '../utils/formStyles.js'

/**
 * Widget initialization
 * Called externally when switching to the tab
 */
export function init(selector = '#vue-api-test') {
  const $el = document.querySelector(selector)

  if (!$el) {
    console.warn(`[API Test] Element ${selector} not found`)
    return null
  }

  if ($el.dataset.vApp === 'true') {
    // Already mounted
    return null
  }

  const app = createMs3VueApp(VueApiTest)
  app.mount(selector)
  injectFormStylesOverride()
  $el.dataset.vApp = 'true'

  return app
}

/**
 * Dev mode - automatic initialization for testing
 */
if (import.meta.env.DEV) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init())
  } else {
    init()
  }
}
