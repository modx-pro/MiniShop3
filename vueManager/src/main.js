import './scss/primevue.scss'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import 'primeicons/primeicons.css'

import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';

import VueProductDataFields from './components/ProductDataFields.vue'

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
 * Обработчик события для монтирования ProductDataFields
 *
 * Вызывается из product.common.js при переключении на вкладку "Данные товара (Vue)"
 */
document.addEventListener('ms3:mountVueProductFields', (e) => {
  console.log('[Vue] Mount event received:', e.detail)

  setTimeout(() => {
    const { targetId, productId } = e.detail
    const $target = document.querySelector(targetId)

    if ($target && $target.dataset.vApp === undefined) {
      console.log('[Vue] Mounting ProductDataFields to:', targetId)

      // Создаём Vue приложение
      const app = createVueApp(VueProductDataFields)

      // Передаём props через provide/inject
      app.provide('productId', productId)
      app.provide('pageKey', 'product_data')

      // Монтируем
      app.mount(targetId)

      // Помечаем что приложение инициализировано
      $target.dataset.vApp = 'true'

      console.log('[Vue] ProductDataFields mounted successfully')
    } else {
      console.warn('[Vue] Target not found or already mounted:', targetId)
    }
  }, 100)
})

console.log('[Vue Manager] main.js loaded')
console.log('[Vue Manager] ms3.config available:', typeof window.ms3 !== 'undefined' && typeof window.ms3.config !== 'undefined')

// Обработчики для других компонентов (FieldsManagement, ApiTest)
// вынесены в отдельные entry points: fields-management.js, api-test.js


