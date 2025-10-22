import './scss/primevue.scss'
import { createApp, h } from 'vue'
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
        darkModeSelector: 'none', // Отключаем автоматическую темную тему
        cssLayer: false, // Отключаем CSS Layer
        // Указываем селектор для CSS переменных вместо :root
        prefix: 'p'
      }
    },
    // Добавляем wrapper класс ко всем компонентам
    pt: {
      directives: {
        tooltip: {
          root: { class: 'vueApp-tooltip' }
        }
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
  setTimeout(() => {
    const { targetId, productId } = e.detail
    const $target = document.querySelector(targetId)

    if ($target && $target.dataset.vApp === undefined) {
      // Создаём wrapper компонент с props
      const WrapperComponent = {
        render() {
          return h(VueProductDataFields, {
            productId: productId
          })
        }
      }

      // Создаём Vue приложение с wrapper
      const app = createVueApp(WrapperComponent)

      // Монтируем
      app.mount(targetId)

      // Помечаем что приложение инициализировано
      $target.dataset.vApp = 'true'
    } else {
      console.warn('[Vue] Target not found or already mounted:', targetId)
    }
  }, 100)
})

// Обработчики для других компонентов (FieldsManagement, ApiTest)
// вынесены в отдельные entry points: fields-management.js, api-test.js


