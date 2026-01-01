/**
 * Entry point for Category Products Grid widget (ES Module)
 *
 * Exports initialization function for mounting Vue application
 * in category update page within ExtJS tab
 */

import '../scss/primevue.scss';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import 'primeicons/primeicons.css';

import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';

import CategoryProductsGrid from '../components/CategoryProductsGrid.vue';

let appInstance = null;

/**
 * Creates and configures Vue application
 * @param {number} categoryId - Category ID
 */
function createVueApp(categoryId) {
  const app = createApp(CategoryProductsGrid, {
    categoryId: categoryId
  });

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
 * Called when category products tab is activated
 * @param {string} selector - DOM selector for mount point
 * @param {number} categoryId - Category ID
 */
export function init(selector = '#ms3-vue-category-products', categoryId = 0) {
  const $el = document.querySelector(selector);

  if (!$el) {
    console.warn('[CategoryProducts] Mount element not found:', selector);
    return null;
  }

  // Already mounted
  if ($el.dataset.vApp === 'true') {
    return appInstance;
  }

  if (!categoryId) {
    console.error('[CategoryProducts] categoryId is required');
    return null;
  }

  appInstance = createVueApp(categoryId);
  appInstance.mount(selector);
  $el.dataset.vApp = 'true';

  return appInstance;
}

/**
 * Unmount and cleanup
 */
export function destroy() {
  if (appInstance) {
    appInstance.unmount();
    appInstance = null;
  }

  const $el = document.querySelector('#ms3-vue-category-products');
  if ($el) {
    $el.dataset.vApp = 'false';
  }
}

/**
 * Check if app is mounted
 */
export function isMounted() {
  const $el = document.querySelector('#ms3-vue-category-products');
  return $el && $el.dataset.vApp === 'true';
}

// Export for global access
window.MS3CategoryProducts = {
  init,
  destroy,
  isMounted
};
