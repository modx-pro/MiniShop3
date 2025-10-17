import './scss/primevue.scss'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import 'primeicons/primeicons.css'

import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';

import VueFieldsManagement from './components/FieldsManagement.vue'

/**
 * MiniShop3 Vue Manager
 *
 * Инициализация Vue приложений для админки MiniShop3
 *
 * Архитектура:
 * - Pinia для state management
 * - Composables для переиспользуемой логики (useApi, useModx, usePermission)
 * - Utils для вспомогательных функций (modx, validation)
 * - Stores для управления состоянием (useProductStore и т.д.)
 * - Request класс для работы с API через connector.php
 *
 * Интеграция с MODX:
 * - Доступ к window.MODx для MODX API
 * - Доступ к window.ms3.config для настроек MiniShop3
 * - HTTP_MODAUTH токен для безопасности
 * - Лексикон для переводов
 */
/**
 * Инициализация Vue приложения с Pinia и сервисами
 *
 * @param {Object} rootComponent - Корневой компонент
 * @returns {Object} - Экземпляр Vue приложения
 */
function createVueApp(rootComponent) {
  const app = createApp(rootComponent);

  // Подключаем Pinia для state management
  const pinia = createPinia();
  app.use(pinia);

  // Подключаем PrimeVue с темой
  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: 'none' // Отключаем автоматическую темную тему
      }
    }
  });

  // Подключаем сервисы PrimeVue
  app.use(ConfirmationService);
  app.use(ToastService);

  return app;
}

/**
 * Обработчик события переключения вкладок ExtJS
 *
 * Ленивая инициализация Vue приложений при активации соответствующих вкладок
 */
document.addEventListener('tabchange', (e) => {
  if (e.detail.name === 'fields') {
    setTimeout(() => {
      const $fieldsManagement = document.querySelector('#vue-fields-management');

      // Проверяем что элемент существует и еще не был инициализирован
      if ($fieldsManagement && $fieldsManagement.dataset.vApp === undefined) {
        const app = createVueApp(VueFieldsManagement);
        app.mount('#vue-fields-management');

        // Помечаем что приложение инициализировано
        $fieldsManagement.dataset.vApp = 'true';
      }
    }, 300);
  }
})

/**
 * Режим разработки (Vite dev server)
 *
 * Автоматически инициализирует Vue приложения для удобства разработки
 */
const isDev = location.hostname === 'localhost';
if (isDev) {
  const $fields = document.querySelector('#vue-fields-management');
  if ($fields) {
    // Эмулируем событие переключения вкладки
    const event = new CustomEvent('tabchange', {
      detail: { name: 'fields' }
    });
    document.dispatchEvent(event);
  }
}


