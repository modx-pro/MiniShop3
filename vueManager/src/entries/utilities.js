/**
 * Utilities page entry — Vue shell instead of Ext panel (#524).
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import UtilitiesPage from '../components/UtilitiesPage.vue'
import { createMs3VueApp } from '../theme/createMs3VueApp.js'
import { injectFormStylesOverride } from '../utils/formStyles.js'

export function init(selector = '#ms3-vue-utilities') {
  const $el = document.querySelector(selector)
  if (!$el || $el.dataset.vApp === 'true') {
    return null
  }

  const app = createMs3VueApp(UtilitiesPage)
  app.mount(selector)
  injectFormStylesOverride()
  $el.dataset.vApp = 'true'

  return app
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => init())
} else {
  init()
}
