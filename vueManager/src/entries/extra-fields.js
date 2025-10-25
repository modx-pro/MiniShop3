/**
 * Entry point для Extra Fields Manager виджета (ES Module)
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
import Tooltip from 'primevue/tooltip';

import VueExtraFieldsManager from '../components/ExtraFieldsManager.vue';

/**
 * Создает и настраивает Vue приложение
 */
function createVueApp() {
  const app = createApp(VueExtraFieldsManager);

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

  // Directives
  app.directive('tooltip', Tooltip);

  return app;
}

/**
 * Инициализация виджета
 * Вызывается извне при переключении на вкладку
 */
export function init(selector = '#ms3-vue-extra-fields') {
  const $el = document.querySelector(selector);

  if (!$el) {
    console.warn(`[Extra Fields Manager] Element ${selector} not found`);
    return null;
  }

  // Проверяем, не смонтирован ли уже
  if ($el.dataset.vApp === 'true') {
    console.info('[Extra Fields Manager] Already mounted');
    return null;
  }

  const app = createVueApp();
  app.mount(selector);
  $el.dataset.vApp = 'true';

  console.info('[Extra Fields Manager] Mounted successfully');

  return app;
}

/**
 * Слушаем событие монтирования от ExtJS
 * { once: true } - гарантирует, что обработчик сработает только один раз
 */
document.addEventListener('ms3:mountVueExtraFields', (e) => {
  const targetId = e.detail?.targetId || '#ms3-vue-extra-fields';
  init(targetId);
}, { once: true });

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
