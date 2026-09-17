/**
 * Utilities page entry — Vue shell instead of Ext panel (#524).
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import { createPinia } from 'pinia'
import { ConfirmationService, PrimeVue, ToastService } from 'primevue'
import { createApp } from 'vue'

import UtilitiesPage from '../components/UtilitiesPage.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'
import { getManagerPrimeVueConfig } from '../utils/primevueTheme.js'

export function init(selector = '#ms3-vue-utilities') {
  const $el = document.querySelector(selector)
  if (!$el || $el.dataset.vApp === 'true') {
    return null
  }

  const app = createApp(UtilitiesPage)
  app.use(createPinia())
  app.use(PrimeVue, getManagerPrimeVueConfig())
  app.use(ConfirmationService)
  app.use(ToastService)
  app.mount(selector)
  injectFormStylesOverride()
  $el.dataset.vApp = 'true'

  return app
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => init())
} else {
  init()
}
