/**
 * Shared Vue app bootstrap for MiniShop3 manager entries.
 */
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import { createApp } from 'vue'

import { scheduleMs3ThemeVars } from './injectMs3ThemeVars.js'
import { ms3Preset } from './ms3Preset.js'

const DEFAULT_THEME_OPTIONS = {
  darkModeSelector: 'none',
}

/**
 * Create a Vue app with shared MiniShop3 PrimeVue setup (Pinia, ms3 preset, toast/confirm).
 *
 * @param {import('vue').Component} rootComponent
 * @param {Record<string, unknown>} [rootProps]
 * @param {{ themeOptions?: Record<string, unknown>, locale?: Record<string, unknown>, primeVue?: Record<string, unknown> }} [options]
 */
export function createMs3VueApp(rootComponent, rootProps, options = {}) {
  const app = createApp(rootComponent, rootProps)

  app.use(createPinia())

  app.use(PrimeVue, {
    theme: {
      preset: ms3Preset,
      options: { ...DEFAULT_THEME_OPTIONS, ...options.themeOptions },
    },
    locale: options.locale ?? getPrimeVueLocale(),
    ...options.primeVue,
  })

  app.use(ConfirmationService)
  app.use(ToastService)

  // Beat VueTools/PrimeVue runtime theme injection (external primevue package).
  scheduleMs3ThemeVars()

  return app
}
