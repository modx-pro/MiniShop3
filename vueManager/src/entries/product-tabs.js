/**
 * Product Tabs Entry Point
 *
 * Creates Vue application with PrimeVue TabView for product editing page.
 * Contains nested tabs: Properties, Gallery, Categories, Links, Options
 * Provides Plugin Registry for third-party extensions.
 */

import '../scss/primevue.scss'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import 'primeicons/primeicons.css'

import ToastService from 'primevue/toastservice'
import ProductTabs from '../components/product/ProductTabs.vue'

/**
 * Plugin Registry for third-party tabs
 * Allows plugins to register additional tabs before or after Vue mount
 */
class ProductTabsRegistry {
  constructor() {
    this.pendingTabs = []
    this._instance = null
    this._mounted = false
  }

  /**
   * Register a new tab
   *
   * @param {Object} tabConfig - Tab configuration
   * @param {string} tabConfig.key - Unique tab key
   * @param {string} tabConfig.title - Tab title (displayed in header)
   * @param {string} tabConfig.type - Tab type: 'vue' or 'extjs'
   * @param {string} [tabConfig.component] - Vue component name (for type: 'vue')
   * @param {string} [tabConfig.xtype] - ExtJS xtype (for type: 'extjs')
   * @param {Object} [tabConfig.extConfig] - ExtJS component config
   * @param {Object} [tabConfig.props] - Vue component props
   * @param {number} [tabConfig.position=100] - Tab position (lower = earlier)
   *
   * @example
   * // Register Vue tab
   * window.MS3ProductTabsRegistry.register({
   *   key: 'variants',
   *   title: 'Варианты',
   *   type: 'vue',
   *   component: 'VariantsTab',
   *   position: 3
   * })
   *
   * @example
   * // Register ExtJS tab
   * window.MS3ProductTabsRegistry.register({
   *   key: 'custom',
   *   title: 'Custom Tab',
   *   type: 'extjs',
   *   xtype: 'my-custom-panel',
   *   extConfig: { foo: 'bar' },
   *   position: 5
   * })
   */
  register(tabConfig) {
    if (!tabConfig.key || !tabConfig.title) {
      console.error('[ProductTabsRegistry] Tab must have key and title', tabConfig)
      return false
    }

    // Check for duplicates in pending
    const existsInPending = this.pendingTabs.some(t => t.key === tabConfig.key)
    if (existsInPending) {
      console.warn(`[ProductTabsRegistry] Tab "${tabConfig.key}" already registered`)
      return false
    }

    // If already mounted, register directly
    if (this._mounted && this._instance) {
      return this._instance.registerPluginTab(tabConfig)
    }

    // Store for later registration
    this.pendingTabs.push(tabConfig)
    return true
  }

  /**
   * Called when Vue app is mounted
   * Registers all pending tabs
   */
  _onMounted(instance) {
    this._instance = instance
    this._mounted = true

    // Register pending tabs
    this.pendingTabs.forEach(tab => {
      if (instance.registerPluginTab) {
        instance.registerPluginTab(tab)
      }
    })
    this.pendingTabs = []
  }

  /**
   * Called when Vue app is unmounted
   */
  _onUnmounted() {
    this._instance = null
    this._mounted = false
  }
}

// Create global registry instance
window.MS3ProductTabsRegistry = window.MS3ProductTabsRegistry || new ProductTabsRegistry()

/**
 * Creates and configures Vue application
 *
 * @param {Object} props - Component props
 * @returns {Object} Vue app instance
 */
function createVueApp(props) {
  const app = createApp(ProductTabs, props)

  const pinia = createPinia()
  app.use(pinia)

  app.use(PrimeVue, {
    theme: {
      preset: Aura,
      options: {
        darkModeSelector: 'none'
      }
    }
  })

  app.use(ToastService)

  return app
}

/**
 * Initialize Product Tabs Vue application
 *
 * @param {Object} config - Configuration object
 * @param {string} [config.containerId='ms3-vue-product-tabs'] - Container element ID
 * @param {number} config.productId - Product ID
 * @param {Object} config.record - Product record data
 * @param {Object} [config.config={}] - Additional configuration (show_gallery, etc.)
 * @returns {Object|null} - App instance and control methods, or null if failed
 */
window.MS3_initProductTabs = function(config) {
  const {
    containerId = 'ms3-vue-product-tabs',
    productId,
    record,
    config: appConfig = {}
  } = config

  if (!productId) {
    console.error('[ProductTabs] productId is required')
    return null
  }

  if (!record) {
    console.error('[ProductTabs] record is required')
    return null
  }

  const container = document.getElementById(containerId)
  if (!container) {
    console.error(`[ProductTabs] Container #${containerId} not found`)
    return null
  }

  // Check if already mounted
  if (container.__vueApp__) {
    console.info('[ProductTabs] Already mounted, returning existing instance')
    return {
      app: container.__vueApp__,
      instance: container.__vueInstance__,
      destroy: () => {
        container.__vueApp__.unmount()
        delete container.__vueApp__
        delete container.__vueInstance__
        window.MS3ProductTabsRegistry._onUnmounted()
      }
    }
  }

  // Merge config from ms3.config if available
  // Note: ms3 is a global variable (not window.ms3) because it's declared with 'let'
  // eslint-disable-next-line no-undef
  const ms3Config = typeof ms3 !== 'undefined' ? ms3.config : {}
  const mergedConfig = {
    show_gallery: true,
    show_categories: true,
    show_links: true,
    show_options: true,
    option_fields: [],
    ...ms3Config,
    ...appConfig
  }

  const props = {
    productId: parseInt(productId),
    record,
    config: mergedConfig
  }

  const app = createVueApp(props)
  const instance = app.mount(container)

  // Store references
  container.__vueApp__ = app
  container.__vueInstance__ = instance

  // Notify registry
  window.MS3ProductTabsRegistry._onMounted(instance)

  return {
    app,
    instance,
    destroy: () => {
      app.unmount()
      delete container.__vueApp__
      delete container.__vueInstance__
      window.MS3ProductTabsRegistry._onUnmounted()
    }
  }
}

/**
 * Destroy Product Tabs instance
 *
 * @param {string} [containerId='ms3-vue-product-tabs'] - Container element ID
 */
window.MS3_destroyProductTabs = function(containerId = 'ms3-vue-product-tabs') {
  const container = document.getElementById(containerId)
  if (container && container.__vueApp__) {
    container.__vueApp__.unmount()
    delete container.__vueApp__
    delete container.__vueInstance__
    window.MS3ProductTabsRegistry._onUnmounted()
  }
}

/**
 * Wait for DOM element to appear
 *
 * @param {string} selector - CSS selector
 * @param {Function} callback - Callback when element found
 * @param {number} [timeout=10000] - Timeout in ms
 */
function waitForElement(selector, callback, timeout = 10000) {
  const element = document.querySelector(selector)
  if (element) {
    callback(element)
    return
  }

  const startTime = Date.now()

  const observer = new MutationObserver(() => {
    const element = document.querySelector(selector)
    if (element) {
      observer.disconnect()
      callback(element)
    } else if (Date.now() - startTime > timeout) {
      observer.disconnect()
      console.warn(`[ProductTabs] Timeout waiting for ${selector}`)
    }
  })

  observer.observe(document.body, {
    childList: true,
    subtree: true
  })
}

/**
 * Listen for mount event from ExtJS
 * ExtJS dispatches this event when the product tab panel is rendered
 */
document.addEventListener('ms3:mountProductTabs', (e) => {
  const {
    targetId = 'ms3-vue-product-tabs',
    productId,
    record,
    config
  } = e.detail || {}

  if (!productId || !record) {
    console.error('[ProductTabs] Event missing required data:', e.detail)
    return
  }

  waitForElement(`#${targetId}`, () => {
    window.MS3_initProductTabs({
      containerId: targetId,
      productId,
      record,
      config
    })
  })
})
