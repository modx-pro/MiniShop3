/**
 * Settings page entry — Vue shell instead of Ext panel (#523).
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import Aura from '@primeuix/themes/aura'
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import { createApp } from 'vue'

import SettingsPage from '../components/SettingsPage.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'

function createVueApp() {
  const app = createApp(SettingsPage)

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

export function init(selector = '#ms3-vue-settings') {
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

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => init())
} else {
  init()
}
