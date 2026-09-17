/**
 * Entry point for Orders Manager page
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import { createPinia } from 'pinia'
import { ConfirmationService, PrimeVue, ToastService } from 'primevue'
import { createApp } from 'vue'

import OrdersGrid from '../components/OrdersGrid.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'
import { getManagerPrimeVueConfig } from '../utils/primevueTheme.js'

/**
 * Creates and configures Vue application
 */
function createVueApp() {
  const app = createApp(OrdersGrid)
  const pinia = createPinia()
  app.use(pinia)

  app.use(PrimeVue, getManagerPrimeVueConfig())

  app.use(ConfirmationService)
  app.use(ToastService)

  return app
}

/**
 * Mount OrdersGrid into the tpl node
 */
export function init(selector = '#ms3-orders-vue-wrapper') {
  const $el = document.querySelector(selector)

  if (!$el || $el.dataset.vApp === 'true') {
    return null
  }

  const app = createVueApp()
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
