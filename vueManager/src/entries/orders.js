/**
 * Entry point for Orders Manager page
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import OrdersGrid from '../components/OrdersGrid.vue'
import { createMs3VueApp } from '../theme/createMs3VueApp.js'
import { injectFormStylesOverride } from '../utils/formStyles.js'

/**
 * Mount OrdersGrid into the tpl node
 */
export function init(selector = '#ms3-orders-vue-wrapper') {
  const $el = document.querySelector(selector)

  if (!$el || $el.dataset.vApp === 'true') {
    return null
  }

  const app = createMs3VueApp(OrdersGrid)
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
