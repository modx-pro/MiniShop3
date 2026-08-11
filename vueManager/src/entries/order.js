/**
 * Entry point for Order View/Edit page
 */

import '../scss/primevue.scss'
import 'primeicons/primeicons.css'

import Aura from '@primeuix/themes/aura'
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import { createApp } from 'vue'

import OrderView from '../components/OrderView.vue'
import { injectFormStylesOverride } from '../utils/formStyles.js'
import {
  snapshotOrderTabConfigForQueue,
  validateOrderPluginTabConfig,
} from '../utils/orderPluginTab.js'

/**
 * Plugin registry for third-party order manager tabs (Vue / ExtJS). See GitHub #166.
 * Same lifecycle as ProductTabsRegistry: call `register()` before or after Vue mount;
 * pre-mount entries are queued (snapshotted) and flushed in `_onMounted(instance)`.
 *
 * Tab config fields:
 * - `key` (string, required) — unique id; must not be info|products|address|history
 * - `title` (string, required) — header label
 * - `type` — `'vue'` (default) or `'extjs'`
 * - `component` — Vue: options object (imported SFC) or registered component name string
 * - `xtype` — ExtJS: component xtype
 * - `extConfig` — extra ExtJS config; merged before core props (see OrderView mountExtJSOrderPlugin)
 * - `props` — extra Vue props (merged before orderId, order, config, isCreateMode)
 * - `position` (number, default 100) — lower sorts earlier
 * - `hideOnCreate` — hide tab while creating a new order (draft flow)
 *
 * Vue and ExtJS tabs receive: `orderId`, `order`, `config` (mgr ms3.config), `isCreateMode`.
 *
 * **Vue tabs** get reactive updates: props change when `order` loads or is edited in the manager.
 *
 * **ExtJS tabs** are created once when the user first opens the tab; `order` / `orderId` / `isCreateMode`
 * are snapshots at creation time. The core does not push later Vue state into the Ext instance — implement
 * `listeners`, a custom `initComponent`, or reload logic inside your xtype if you need live data.
 *
 * @example Vue tab (prefer a component definition from your bundle; string names need app.component())
 * window.MS3OrderTabsRegistry.register({
 *   key: 'tracking',
 *   title: 'Tracking',
 *   type: 'vue',
 *   component: MyTrackingTab,
 *   position: 10,
 * })
 *
 * @example ExtJS tab — extConfig merges first; order, orderId, config, isCreateMode override extConfig keys
 * window.MS3OrderTabsRegistry.register({
 *   key: 'delivery',
 *   title: 'Delivery',
 *   type: 'extjs',
 *   xtype: 'my-delivery-panel',
 *   extConfig: { foo: 1 },
 * })
 */
class OrderTabsRegistry {
  constructor() {
    /** @type {object[]} snapshotted tab configs (see `snapshotOrderTabConfigForQueue`) queued before mount */
    this.pendingTabs = []
    /** @type {import('vue').ComponentPublicInstance | null} */
    this._instance = null
    this._mounted = false
  }

  /**
   * Before mount, valid configs are queued as a shallow snapshot (see `snapshotOrderTabConfigForQueue`)
   * so later mutations of the caller’s object do not change the queued registration.
   *
   * @param {object} tabConfig
   * @returns {boolean} false if validation failed or duplicate key
   */
  register(tabConfig) {
    if (this._mounted && this._instance) {
      return this._instance.registerPluginTab(tabConfig)
    }

    const validation = validateOrderPluginTabConfig(tabConfig)
    if (!validation.ok) {
      console.error(`[OrderTabsRegistry] ${validation.reason}`, tabConfig)
      return false
    }

    const existsInPending = this.pendingTabs.some(t => t.key === tabConfig.key)
    if (existsInPending) {
      console.warn(`[OrderTabsRegistry] Tab "${tabConfig.key}" already registered`)
      return false
    }

    this.pendingTabs.push(snapshotOrderTabConfigForQueue(tabConfig))
    return true
  }

  /**
   * Called from `init()` after app.mount(). Flushes queued configs through `registerPluginTab` (single normalize).
   * @param {import('vue').ComponentPublicInstance} instance
   */
  _onMounted(instance) {
    this._instance = instance
    this._mounted = true

    this.pendingTabs.forEach(tab => {
      if (instance.registerPluginTab) {
        instance.registerPluginTab(tab)
      }
    })
    this.pendingTabs = []
  }

  /** Called from OrderView onBeforeUnmount; clears root so new registrations can queue again */
  _onUnmounted() {
    this._instance = null
    this._mounted = false
  }
}

// Preserve pendingTabs from early registrations (plugins that run before this module)
const earlyPending = window.MS3OrderTabsRegistry?.pendingTabs || []
window.MS3OrderTabsRegistry = new OrderTabsRegistry()
earlyPending.forEach(tab => window.MS3OrderTabsRegistry.register(tab))

/**
 * Creates and configures Vue application
 */
function createVueApp() {
  const app = createApp(OrderView)
  const pinia = createPinia()
  app.use(pinia)

  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: 'none',
      },
    },
    locale: getPrimeVueLocale(),
  })

  app.use(ConfirmationService)
  app.use(ToastService)

  return app
}

/**
 * Mount OrderView into the tpl node
 *
 * @returns {import('vue').App | null}
 */
export function init(selector = '#ms3-order-vue-wrapper') {
  const $el = document.querySelector(selector)

  if (!$el || $el.dataset.vApp === 'true') {
    return null
  }

  const app = createVueApp()
  const instance = app.mount(selector)
  injectFormStylesOverride()
  $el.dataset.vApp = 'true'

  // Flush queued plugin tabs into OrderView (defineExpose registerPluginTab)
  window.MS3OrderTabsRegistry._onMounted(instance)

  return app
}

/**
 * Automatic initialization on DOM ready
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => init())
} else {
  init()
}
