/**
 * Entry point for Notification Center widget (ES Module)
 *
 * Exports initialization function for mounting Vue application
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import NotificationsGrid from '../components/NotificationsGrid.vue'
import { createMs3VueApp } from '../theme/createMs3VueApp.js'
import { injectFormStylesOverride } from '../utils/formStyles.js'

/**
 * Widget initialization
 */
export function init(selector = '#ms3-notifications-vue-wrapper') {
  const $el = document.querySelector(selector)

  if (!$el || $el.dataset.vApp === 'true') {
    return null
  }

  const app = createMs3VueApp(NotificationsGrid)
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
