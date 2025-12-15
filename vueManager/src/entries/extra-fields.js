/**
 * Entry point for Extra Fields Manager widget (ES Module)
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
import Tooltip from 'primevue/tooltip';

import VueExtraFieldsManager from '../components/ExtraFieldsManager.vue';

/**
 * Creates and configures Vue application
 */
function createVueApp() {
  const app = createApp(VueExtraFieldsManager);

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

  app.directive('tooltip', Tooltip);

  return app;
}

/**
 * Widget initialization
 * Called externally when switching to the tab
 */
export function init(selector = '#ms3-vue-extra-fields') {
  const $el = document.querySelector(selector);

  if (!$el) {
    console.warn(`[Extra Fields Manager] Element ${selector} not found`);
    return null;
  }

  if ($el.dataset.vApp === 'true') {
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
document.addEventListener('ms3:mountVueExtraFields', (e) => {
  const targetId = e.detail?.targetId || '#ms3-vue-extra-fields';
  init(targetId);
}, { once: true });

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
