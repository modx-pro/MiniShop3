/**
 * Entry point for Utilities Gallery page (ES Module)
 *
 * Exports initialization function for mounting Vue application
 */

import '../scss/primevue.scss';
import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import 'primeicons/primeicons.css';

import ToastService from 'primevue/toastservice';

import UtilitiesGallery from '../components/UtilitiesGallery.vue';

let app = null;

/**
 * Creates and configures Vue application
 */
function createVueApp(props = {}) {
  const vueApp = createApp(UtilitiesGallery, props);

  vueApp.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        prefix: 'p',
        darkModeSelector: '.ms3-dark-mode',
        cssLayer: false
      }
    }
  });

  vueApp.use(ToastService);

  return vueApp;
}

/**
 * Widget initialization
 */
export function init(selector = '#ms3-vue-utilities-gallery') {
  const $el = document.querySelector(selector);

  if (!$el) {
    return null;
  }

  if ($el.dataset.vApp === 'true') {
    return null;
  }

  // Unmount existing app if any
  if (app) {
    app.unmount();
  }

  // Component reads config from data-attributes set by ExtJS
  app = createVueApp();
  app.mount(selector);
  $el.dataset.vApp = 'true';

  return app;
}

/**
 * Unmount the application
 */
export function unmount() {
  if (app) {
    app.unmount();
    app = null;
  }
}

/**
 * Wait for ExtJS to create DOM element
 */
function waitForElement(selector, callback) {
  const element = document.querySelector(selector);

  if (element) {
    callback(element);
    return;
  }

  const observer = new MutationObserver((mutations) => {
    const element = document.querySelector(selector);
    if (element) {
      observer.disconnect();
      callback(element);
    }
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true
  });
}

// Listen for mount event from ExtJS
document.addEventListener('ms3:mountVueUtilitiesGallery', (event) => {
  const targetId = event.detail?.targetId || '#ms3-vue-utilities-gallery';
  init(targetId);
});

/**
 * Automatic initialization on DOM load
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    waitForElement('#ms3-vue-utilities-gallery', () => init());
  });
} else {
  waitForElement('#ms3-vue-utilities-gallery', () => init());
}

// Export for programmatic usage
export default { init, unmount };
