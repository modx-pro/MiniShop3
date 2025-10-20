/**
 * Entry point для API Test виджета (ES Module)
 *
 * Экспортирует функцию инициализации для монтирования Vue приложения
 */

import '../scss/primevue.scss';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import 'primeicons/primeicons.css';

import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';

import VueApiTest from '../components/ApiTest.vue';

/**
 * Создает и настраивает Vue приложение
 */
function createVueApp() {
  const app = createApp(VueApiTest);

  // Pinia
  const pinia = createPinia();
  app.use(pinia);

  // PrimeVue
  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: 'none'
      }
    }
  });

  // PrimeVue сервисы
  app.use(ConfirmationService);
  app.use(ToastService);

  return app;
}

/**
 * Инициализация виджета
 * Вызывается извне при переключении на вкладку
 */
export function init(selector = '#vue-api-test') {
  const $el = document.querySelector(selector);

  if (!$el) {
    console.warn(`[API Test] Element ${selector} not found`);
    return null;
  }

  // Проверяем, не смонтирован ли уже
  if ($el.dataset.vApp === 'true') {
    console.info('[API Test] Already mounted');
    return null;
  }

  const app = createVueApp();
  app.mount(selector);
  $el.dataset.vApp = 'true';

  console.info('[API Test] Mounted successfully');
  return app;
}

/**
 * Dev режим - автоматическая инициализация для тестирования
 */
if (import.meta.env.DEV) {
  // Ждем готовности DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
  } else {
    init();
  }
}
