/**
 * Entry point для Customers Manager виджета (ES Module)
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

import CustomersGrid from '../components/CustomersGrid.vue';

/**
 * Создает и настраивает Vue приложение
 */
function createVueApp() {
  const app = createApp(CustomersGrid);

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
 * Вызывается при загрузке страницы управления клиентами
 */
export function init(selector = '#ms3-customers-vue-wrapper') {
  const $el = document.querySelector(selector);

  if (!$el) {
    return null;
  }

  // Проверяем, не смонтирован ли уже
  if ($el.dataset.vApp === 'true') {
    return null;
  }

  const app = createVueApp();
  app.mount(selector);
  $el.dataset.vApp = 'true';

  return app;
}

/**
 * Ждем, пока ExtJS создаст DOM элемент
 * Используем MutationObserver для отслеживания появления элемента
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

/**
 * Автоматическая инициализация при загрузке DOM
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    waitForElement('#ms3-customers-vue-wrapper', () => init());
  });
} else {
  waitForElement('#ms3-customers-vue-wrapper', () => init());
}
