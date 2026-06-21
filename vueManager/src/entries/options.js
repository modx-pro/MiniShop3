/**
 * MiniShop3 - Options Grid Entry Point
 *
 * Vue application for managing product options (Settings → Options tab).
 * Replaces legacy ExtJS ms3-tree-option-categories + ms3-grid-option combo.
 */

import Aura from '@primeuix/themes/aura'
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import { createApp } from 'vue'

import OptionsAndGroupsTabs from '../components/OptionsAndGroupsTabs.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'

const MOUNT_ID = 'ms3-vue-options'

let app = null

function mountApp() {
  const container = document.getElementById(MOUNT_ID)
  if (!container) return false
  if (app) return true

  app = createApp(OptionsAndGroupsTabs)

  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: '.dark-mode',
        cssLayer: false,
      },
    },
    locale: getPrimeVueLocale(),
  })
  app.use(ToastService)
  app.use(ConfirmationService)

  app.mount(container)
  injectFormStylesOverride()

  return true
}

function unmountApp() {
  if (app) {
    app.unmount()
    app = null
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountApp)
} else {
  mountApp()
}

if (typeof Ext !== 'undefined') {
  Ext.onReady(function () {
    mountApp()
    setInterval(() => {
      const container = document.getElementById(MOUNT_ID)
      if (container && container.offsetParent !== null) {
        mountApp()
      }
    }, 500)
  })
}

export { mountApp, unmountApp }
