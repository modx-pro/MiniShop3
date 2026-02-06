import './scss/primevue.scss'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import 'primeicons/primeicons.css'

import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';

/**
 * MiniShop3 Vue Manager
 *
 * Initialization of Vue applications for MiniShop3 admin panel
 *
 * Architecture:
 * - Pinia for state management
 * - Composables for reusable logic (useApi, useModx, usePermission)
 * - Utils for helper functions (modx, validation)
 * - Stores for state management (useProductStore, etc.)
 * - Request class for working with API through connector.php
 *
 * MODX Integration:
 * - Access to window.MODx for MODX API
 * - Access to ms3.config for MiniShop3 settings (global variable, not window.ms3)
 * - HTTP_MODAUTH token for security
 * - Lexicon for translations
 */
/**
 * Initialize Vue application with Pinia and services
 *
 * @param {Object} rootComponent - Root component
 * @returns {Object} - Vue application instance
 */
function createVueApp(rootComponent) {
  const app = createApp(rootComponent);

  const pinia = createPinia();
  app.use(pinia);

  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: 'none',
        cssLayer: false,
        prefix: 'p'
      }
    },
    pt: {
      directives: {
        tooltip: {
          root: { class: 'vueApp-tooltip' }
        }
      }
    }
  });

  app.use(ConfirmationService);
  app.use(ToastService);

  return app;
}
