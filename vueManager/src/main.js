import './scss/primevue.scss'
import 'primeicons/primeicons.css'

import { createMs3VueApp } from './theme/createMs3VueApp.js'

/**
 * MiniShop3 Vue Manager — shared bootstrap for MODX admin SPA entries.
 * Pinia, PrimeVue (ms3 preset), ConfirmationService, ToastService.
 * MODX: window.MODx, ms3.config, HTTP_MODAUTH, lexicon via @vuetools/useLexicon.
 */
export function createVueApp(rootComponent) {
  return createMs3VueApp(rootComponent, undefined, {
    themeOptions: {
      cssLayer: false,
      prefix: 'p',
    },
    primeVue: {
      pt: {
        directives: {
          tooltip: {
            root: { class: 'vueApp-tooltip' },
          },
        },
      },
    },
  })
}
