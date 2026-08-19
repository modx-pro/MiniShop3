/**
 * Validation and normalization for `window.MS3OrderTabsRegistry` / OrderView plugin tabs.
 * Keeps rules in one place for both the pre-mount queue (order.js) and direct registration.
 */

/** Keys used by built-in tabs; plugin tabs must use different keys */
export const RESERVED_ORDER_TAB_KEYS = new Set([
  'info',
  'products',
  'address',
  'tracking',
  'history',
])

/**
 * Shallow snapshot for the pre-mount queue: top-level fields and nested `extConfig` / `props` are
 * copied so plugins cannot invalidate an already accepted registration by mutating the same object
 * before Vue mounts. The Vue `component` reference is kept as-is (definitions are not cloned).
 *
 * @param {object} tabConfig
 * @returns {object}
 */
export function snapshotOrderTabConfigForQueue(tabConfig) {
  if (!tabConfig || typeof tabConfig !== 'object') {
    return tabConfig
  }
  const ext =
    tabConfig.extConfig != null && typeof tabConfig.extConfig === 'object'
      ? { ...tabConfig.extConfig }
      : {}
  const props =
    tabConfig.props != null && typeof tabConfig.props === 'object' ? { ...tabConfig.props } : {}
  return {
    ...tabConfig,
    extConfig: ext,
    props,
  }
}

/**
 * @param {object} tabConfig
 * @returns {{ ok: true, type: string } | { ok: false, reason: string }}
 */
export function validateOrderPluginTabConfig(tabConfig) {
  if (!tabConfig?.key || !tabConfig?.title) {
    return { ok: false, reason: 'Tab must have key and title' }
  }
  if (RESERVED_ORDER_TAB_KEYS.has(tabConfig.key)) {
    return { ok: false, reason: `Tab key "${tabConfig.key}" is reserved` }
  }
  const type = (tabConfig.type || 'vue').toLowerCase()
  if (type !== 'vue' && type !== 'extjs') {
    return { ok: false, reason: `Unsupported tab type "${tabConfig.type}" (use vue or extjs)` }
  }
  if (type === 'vue' && !tabConfig.component) {
    return { ok: false, reason: 'Vue tab requires component (component name or definition)' }
  }
  if (type === 'extjs' && !tabConfig.xtype) {
    return { ok: false, reason: 'ExtJS tab requires xtype' }
  }
  return { ok: true, type }
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
  const validation = validateOrderPluginTabConfig(tabConfig)
  if (!validation.ok) {
    return { ok: false, reason: validation.reason }
  }
  return {
    ok: true,
    tab: {
      key: tabConfig.key,
      title: tabConfig.title,
      type: validation.type,
      component: tabConfig.component,
      xtype: tabConfig.xtype,
      extConfig: tabConfig.extConfig || {},
      props: tabConfig.props || {},
      position: tabConfig.position ?? 100,
      hideOnCreate: !!tabConfig.hideOnCreate,
    },
  }
}
