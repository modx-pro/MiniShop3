/**
 * Entry point для Fields Management виджета (ES Module)
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

import VueFieldsManagement from '../components/FieldsManagement.vue';

/**
 * Создает и настраивает Vue приложение
 */
function createVueApp() {
  const app = createApp(VueFieldsManagement);

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
export function init(selector = '#vue-fields-management') {
  const $el = document.querySelector(selector);

  if (!$el) {
    console.warn(`[Fields Management] Element ${selector} not found`);
    return null;
  }

  // Проверяем, не смонтирован ли уже
  if ($el.dataset.vApp === 'true') {
    console.info('[Fields Management] Already mounted');
    return null;
  }

  const app = createVueApp();
  app.mount(selector);
  $el.dataset.vApp = 'true';

  console.info('[Fields Management] Mounted successfully');
  return app;
}

/**
 * Слушаем событие монтирования от ExtJS
 */
document.addEventListener('ms3:mountVueFieldsManagement', (e) => {
  console.log('[Fields Management] Mount event received', e.detail);
  const targetId = e.detail?.targetId || '#ms3-vue-fields-management';
  init(targetId);
});

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
