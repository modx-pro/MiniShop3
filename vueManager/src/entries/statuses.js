/**
 * MiniShop3 - Statuses Grid Entry Point
 *
 * Vue application for managing order statuses in admin panel
 */

import Aura from '@primeuix/themes/aura'
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import { createApp } from 'vue'

import StatusesGrid from '../components/StatusesGrid.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'

// Mount point ID
const MOUNT_ID = 'ms3-vue-statuses'

// Track if app is mounted
let app = null

/**
 * Initialize and mount Vue app
 */
function mountApp() {
  const container = document.getElementById(MOUNT_ID)

  if (!container) {
    return false
  }

  // Don't mount twice
  if (app) {
    return true
  }

  app = createApp(StatusesGrid)

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

/**
 * Unmount Vue app
 */
function unmountApp() {
  if (app) {
    app.unmount()
    app = null
  }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountApp)
} else {
  mountApp()
}

// Listen for ExtJS tab activation
if (typeof Ext !== 'undefined') {
  Ext.onReady(function () {
    // Try to mount on Ext ready
    mountApp()

    // Listen for tab changes
    const checkAndMount = () => {
      const container = document.getElementById(MOUNT_ID)
      if (container && container.offsetParent !== null) {
        mountApp()
      }
    }

    // Check periodically for tab visibility
    setInterval(checkAndMount, 500)
  })
}

export { mountApp, unmountApp }
