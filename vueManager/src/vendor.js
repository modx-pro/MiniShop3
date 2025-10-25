/**
 * Vendor bundle - общие библиотеки для всех Vue виджетов
 *
 * Этот файл собирается в отдельный UMD bundle и загружается ПЕРВЫМ,
 * до любых виджетов. Все виджеты используют эти библиотеки как externals.
 *
 * Экспортирует глобальные переменные:
 * - window.Vue
 * - window.Pinia
 * - window.PrimeVue
 * - window.PrimeVueThemes
 */

import * as Vue from 'vue';
import * as Pinia from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';

// Экспортируем в глобальную область
window.Vue = Vue;
window.Pinia = Pinia;
window.PrimeVue = {
  Config: PrimeVue,
  Themes: {
    Aura
  },
  Services: {
    ConfirmationService,
    ToastService
  }
};

// Для отладки
if (import.meta.env.DEV) {
  console.log('[MS3 Vendor] Libraries loaded:', {
    Vue: !!window.Vue,
    Pinia: !!window.Pinia,
    PrimeVue: !!window.PrimeVue
  });
}
