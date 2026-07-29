/**
 * Plugin tab registry integration for the order view (built-in + MS3OrderTabsRegistry tabs).
 *
 * @param {Object} deps
 * @param {Function} deps._
 * @param {import('vue').Ref} deps.order
 * @param {import('vue').ComputedRef} deps.orderId
 * @param {import('vue').ComputedRef} deps.isCreateMode
 * @param {import('vue').ComputedRef} deps.managerConfig
 */
import { computed, ref } from 'vue'

import { normalizeOrderPluginTab } from '../utils/orderPluginTab.js'

export function useOrderPluginTabs(deps) {
  const { _, order, orderId, isCreateMode, managerConfig } = deps

  const orderActiveTab = ref('info')
  const pluginTabs = ref([])
  /** ExtJS plugin panels mounted lazily per tab key; destroyed in onBeforeUnmount */
  const mountedExtPluginComponents = ref({})

  /** Fixed positions 0–3; must stay in sync with RESERVED_ORDER_TAB_KEYS in orderPluginTab.js */
  const builtInOrderTabs = computed(() => [
    { key: 'info', title: _('order_info'), position: 0, hideOnCreate: false, kind: 'builtin' },
    {
      key: 'products',
      title: _('order_products'),
      position: 1,
      hideOnCreate: true,
      kind: 'builtin',
    },
    {
      key: 'address',
      title: _('order_address'),
      position: 2,
      hideOnCreate: false,
      kind: 'builtin',
    },
    { key: 'history', title: _('order_history'), position: 3, hideOnCreate: true, kind: 'builtin' },
  ])

  /** Built-in + plugin tabs, sorted by `position`; respects hideOnCreate per tab */
  const orderTabsConfig = computed(() => {
    const builtIn = builtInOrderTabs.value.filter(t => !(t.hideOnCreate && isCreateMode.value))
    const plugins = pluginTabs.value
      .filter(t => !(t.hideOnCreate && isCreateMode.value))
      .map(t => ({ ...t, kind: 'plugin' }))
    return [...builtIn, ...plugins].sort((a, b) => (a.position ?? 100) - (b.position ?? 100))
  })

  /**
   * Registers a plugin tab (called by window.MS3OrderTabsRegistry or tests).
   * Validation lives in normalizeOrderPluginTab().
   */
  function registerPluginTab(tabData) {
    const normalized = normalizeOrderPluginTab(tabData)
    if (!normalized.ok) {
      console.error(`[OrderView] ${normalized.reason}`, tabData)
      return false
    }
    const exists = pluginTabs.value.some(t => t.key === normalized.tab.key)
    if (exists) {
      console.warn(`[OrderView] Tab with key "${normalized.tab.key}" already registered`)
      return false
    }
    pluginTabs.value.push(normalized.tab)
    return true
  }

  /**
   * Props passed to Vue plugin tab components (same contract as ExtJS tabs below).
   * User `tab.props` is spread first; core fields override name collisions intentionally.
   */
  function pluginVueProps(tab) {
    return {
      ...(tab.props || {}),
      orderId: orderId.value,
      order: order.value,
      config: managerConfig.value,
      isCreateMode: isCreateMode.value,
    }
  }

  /** Waits for TabPanel DOM element via MutationObserver (consistent with other entry points). */
  function waitForOrderTabElement(id, callback) {
    const element = document.getElementById(id)
    if (element) {
      callback(element)
      return
    }
    const observer = new MutationObserver(() => {
      const el = document.getElementById(id)
      if (el) {
        observer.disconnect()
        callback(el)
      }
    })
    observer.observe(document.body, { childList: true, subtree: true })
  }

  /**
   * Lazy-mounts an ExtJS panel into the plugin tab container.
   * Merge order: xtype/renderTo/width, then extConfig, then core fields (order, orderId, config, isCreateMode).
   *
   * The Ext instance is created once per tab key when the user first selects the tab. Later changes to
   * Vue’s `order` (after API load, save, etc.) are not pushed into Ext — plugin panels must implement
   * their own listeners, polling, or `load` hooks if they need live data.
   */
  function mountExtJSOrderPlugin(tab) {
    if (mountedExtPluginComponents.value[tab.key]) {
      return
    }
    const containerId = `ms3-order-tab-${tab.key}`
    waitForOrderTabElement(containerId, container => {
      try {
        if (typeof Ext === 'undefined') {
          console.error('[OrderView] Ext is not defined')
          return
        }
        const extComponent = Ext.create({
          xtype: tab.xtype,
          renderTo: container,
          width: '100%',
          ...tab.extConfig,
          order: order.value,
          orderId: orderId.value,
          config: managerConfig.value,
          isCreateMode: isCreateMode.value,
        })
        mountedExtPluginComponents.value[tab.key] = extComponent
      } catch (error) {
        console.error(`[OrderView] Failed to mount ExtJS order tab ${tab.key}:`, error)
      }
    })
  }

  function destroyPluginExtComponents() {
    Object.keys(mountedExtPluginComponents.value).forEach(key => {
      const component = mountedExtPluginComponents.value[key]
      if (component && typeof component.destroy === 'function') {
        try {
          component.destroy()
        } catch (e) {
          console.warn(`[OrderView] Error destroying plugin ExtJS component ${key}:`, e)
        }
      }
    })
    mountedExtPluginComponents.value = {}
  }

  return {
    orderActiveTab,
    pluginTabs,
    mountedExtPluginComponents,
    builtInOrderTabs,
    orderTabsConfig,
    registerPluginTab,
    pluginVueProps,
    mountExtJSOrderPlugin,
    destroyPluginExtComponents,
  }
}
