/**
 * Shared Vue app bootstrap for MiniShop3 manager entries.
 * Theme comes from VueTools via getManagerPrimeVueConfig (#738).
 */
import { createPinia } from 'pinia'
import { ConfirmationService, PrimeVue, ToastService } from 'primevue'
import { createApp } from 'vue'

import { getManagerPrimeVueConfig, shouldInjectFormStylesOverride } from '../utils/primevueTheme.js'
import { scheduleMs3ThemeVars } from './injectMs3ThemeVars.js'

/**
 * Create a Vue app with shared MiniShop3 PrimeVue setup (Pinia, theme, toast/confirm).
 *
 * @param {import('vue').Component} rootComponent
 * @param {Record<string, unknown>} [rootProps]
 * @param {{ pt?: object, locale?: Record<string, unknown> } & Record<string, unknown>} [options]
 *        Extra PrimeVue config merged into getManagerPrimeVueConfig (e.g. `pt`).
 */
export function createMs3VueApp(rootComponent, rootProps, options = {}) {
  const app = createApp(rootComponent, rootProps)

  app.use(createPinia())
  app.use(PrimeVue, getManagerPrimeVueConfig(options))
  app.use(ConfirmationService)
  app.use(ToastService)

  // Aura-only: force MODX-aligned --p-primary-* after VueTools theme injection.
  // Modx preset owns its tokens (#738) — skip there.
  if (shouldInjectFormStylesOverride()) {
    scheduleMs3ThemeVars()
  }

  return app
}
