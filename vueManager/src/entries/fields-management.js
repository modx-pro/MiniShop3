/**
 * Entry point for Fields Management widget (ES Module)
 *
 * Exports initialization function for mounting Vue application
 */

import '../scss/primevue.scss';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import 'primeicons/primeicons.css';

import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';

import VueFieldsManagement from '../components/FieldsManagement.vue';

/**
 * Creates and configures Vue application
 */
function createVueApp() {
  const app = createApp(VueFieldsManagement);

  const pinia = createPinia();
  app.use(pinia);

  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: 'none'
      }
    }
  });

  app.use(ConfirmationService);
  app.use(ToastService);

  return app;
}

/**
 * Widget initialization
 * Called externally when switching to the tab
 */
export function init(selector = '#vue-fields-management') {
  const $el = document.querySelector(selector);

  if (!$el) {
    console.warn(`[Fields Management] Element ${selector} not found`);
    return null;
  }

  if ($el.dataset.vApp === 'true') {
    console.info('[Fields Management] Already mounted');
    return null;
  }

  const app = createVueApp();
  app.mount(selector);
  $el.dataset.vApp = 'true';

  return app;
}

/**
 * Listen for mount event from ExtJS
 */
document.addEventListener('ms3:mountVueFieldsManagement', (e) => {
  const targetId = e.detail?.targetId || '#ms3-vue-fields-management';
  init(targetId);
});

/**
 * Dev mode - automatic initialization for testing
 */
if (import.meta.env.DEV) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
  } else {
    init();
  }
}
