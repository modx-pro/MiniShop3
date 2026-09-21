/**
 * Entry point for Help Page Vue application
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import HelpPage from '../components/HelpPage.vue'
import { createMs3VueApp } from '../theme/createMs3VueApp.js'
import { injectFormStylesOverride } from '../utils/formStyles.js'

/**
 * Widget initialization
 */
export function init(selector = '#ms3-vue-help') {
  const $el = document.querySelector(selector)

  if (!$el || $el.dataset.vApp === 'true') {
    return null
  }

  const app = createMs3VueApp(HelpPage)
  app.mount(selector)
  injectFormStylesOverride()
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
