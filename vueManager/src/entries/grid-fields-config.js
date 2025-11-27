/**
 * Entry point для Grid Fields Config виджета (ES Module)
 *
 * Экспортирует функцию инициализации для монтирования Vue приложения
 */

import '../scss/primevue.scss'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import 'primeicons/primeicons.css'

import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'

import GridFieldsConfig from '../components/GridFieldsConfig.vue'

/**
 * Создает и настраивает Vue приложение
 */
function createVueApp() {
  const app = createApp(GridFieldsConfig)

  // Pinia
  const pinia = createPinia()
  app.use(pinia)

  // PrimeVue
  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: 'none'
      }
    }
  })

  // PrimeVue сервисы
  app.use(ConfirmationService)
  app.use(ToastService)

  return app
}

/**
 * Инициализация виджета
 * Вызывается при загрузке страницы конфигурации гридов
 */
export function init(selector = '#ms3-grid-fields-config-vue-wrapper') {
  const $el = document.querySelector(selector)

  if (!$el) {
    return null
  }

  // Проверяем, не смонтирован ли уже
  if ($el.dataset.vApp === 'true') {
    return null
  }

  const app = createVueApp()
  app.mount(selector)
  $el.dataset.vApp = 'true'

  return app
}

/**
 * Слушаем кастомное событие от ExtJS панели
 * Монтируем приложение когда вкладка рендерится
 */
document.addEventListener('ms3:mountVueGridFieldsConfig', (event) => {
  const targetId = event.detail?.targetId || '#ms3-grid-fields-config-vue-wrapper'
  init(targetId)
})
