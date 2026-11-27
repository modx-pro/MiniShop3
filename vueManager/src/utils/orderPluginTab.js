/**
 * Validation and normalization for `window.MS3OrderTabsRegistry` / OrderView plugin tabs.
 * Rules are shared with the product registry via `pluginTab.js`.
 */
import { normalizePluginTab, validatePluginTabConfig } from './pluginTab.js'

export { snapshotPluginTabConfigForQueue as snapshotOrderTabConfigForQueue } from './pluginTab.js'

/** Keys used by built-in tabs; plugin tabs must use different keys.
 * Built-in shipment tab uses `ms3_shipment` so docs/examples can keep `tracking`.
 */
export const RESERVED_ORDER_TAB_KEYS = new Set([
  'info',
  'products',
  'address',
  'ms3_shipment',
  'history',
])

/**
 * @param {object} tabConfig
 */
export function validateOrderPluginTabConfig(tabConfig) {
  return validatePluginTabConfig(tabConfig, RESERVED_ORDER_TAB_KEYS)
}

/**
 * Validates and returns the tab object stored in OrderView (single place for rules + shape).
 *
 * **Vue (`type: 'vue'`)** — `component` must be a component options object (e.g. imported SFC),
 * or a string name already registered on the app (`app.component(...)`). The core does not
 * register arbitrary component names from plugins.
 *
 * **ExtJS (`type: 'extjs'`)** — OrderView passes `order`, `orderId`, `config`, `isCreateMode` into
 * `Ext.create` after `extConfig`; values are fixed at first mount of that tab. For data that arrives
 * after load (e.g. async order fetch), panels must subscribe to events or refresh themselves — the
 * core does not update the Ext component when Vue’s `order` ref changes.
 *
 * @param {object} tabConfig
 * @returns {{ ok: true, tab: object } | { ok: false, reason: string }}
 */
export function normalizeOrderPluginTab(tabConfig) {
  return normalizePluginTab(tabConfig, RESERVED_ORDER_TAB_KEYS)
}
