/**
 * Entry point for Orders Manager widget (ES Module)
 *
 * Exports initialization function for mounting Vue application
 */

import '../scss/primevue.scss';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale';
import 'primeicons/primeicons.css';

import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';

import OrdersGrid from '../components/OrdersGrid.vue';

/**
 * Creates and configures Vue application
 */
function createVueApp() {
  const app = createApp(OrdersGrid);

  const pinia = createPinia();
  app.use(pinia);

  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: 'none',
      },
    },
    locale: getPrimeVueLocale(),
  });

  app.use(ConfirmationService);
  app.use(ToastService);

  return app;
}

/**
 * Widget initialization
 * Called when orders management page loads
 */
export function init(selector = '#ms3-orders-vue-wrapper') {
  const $el = document.querySelector(selector);

  if (!$el) {
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
 * Wait for ExtJS to create DOM element
 * Uses MutationObserver to track element appearance
 */
function waitForElement(selector, callback) {
  const element = document.querySelector(selector);

  if (element) {
    callback(element);
    return;
  }

  const observer = new MutationObserver(() => {
    const element = document.querySelector(selector);
    if (element) {
      observer.disconnect();
      callback(element);
    }
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
  });
}

/**
 * Automatic initialization on DOM load
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    waitForElement('#ms3-orders-vue-wrapper', () => init());
  });
} else {
  waitForElement('#ms3-orders-vue-wrapper', () => init());
}
