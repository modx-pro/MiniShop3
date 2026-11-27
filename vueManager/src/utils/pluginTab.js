/**
 * Shared helpers for the MS3 manager plugin tab registries (order and product).
 * Keeps validation, queue snapshots and early-stub handoff identical on both pages.
 */

/** Product built-in tab keys; plugin tabs must use different keys. */
export const RESERVED_PRODUCT_TAB_KEYS = new Set([
  'properties',
  'gallery',
  'categories',
  'links',
  'options',
])

/**
 * Shallow snapshot for the pre-mount queue: top-level fields and nested `extConfig` / `props` are
 * copied so later mutations of the caller's object cannot change an accepted registration.
 * A Vue `component` definition is kept as-is (component definitions are not cloned).
 *
 * @param {object} tabConfig
 * @returns {object}
 */
export function snapshotPluginTabConfigForQueue(tabConfig) {
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
 * @param {Set<string>} [reservedKeys]
 * @param {string[]} [allowedTypes] `plugin-extjs` is a legacy product-only alias
 * @returns {{ ok: true, type: string } | { ok: false, reason: string }}
 */
export function validatePluginTabConfig(
  tabConfig,
  reservedKeys = new Set(),
  allowedTypes = ['vue', 'extjs']
) {
  if (!tabConfig?.key || !tabConfig?.title) {
    return { ok: false, reason: 'Tab must have key and title' }
  }
  if (reservedKeys.has(tabConfig.key)) {
    return { ok: false, reason: `Tab key "${tabConfig.key}" is reserved` }
  }
  const type = String(tabConfig.type || 'vue').toLowerCase()
  if (!allowedTypes.includes(type)) {
    return {
      ok: false,
      reason: `Unsupported tab type "${tabConfig.type}" (use ${allowedTypes.join(' or ')})`,
    }
  }
  if (type === 'vue' && !tabConfig.component) {
    return { ok: false, reason: 'Vue tab requires component (component name or definition)' }
  }
  if (type !== 'vue' && !tabConfig.xtype) {
    return { ok: false, reason: 'ExtJS tab requires xtype' }
  }
  return { ok: true, type }
}

/**
 * Validate and produce the canonical shape stored by the Vue component.
 *
 * @param {object} tabConfig
 * @param {Set<string>} [reservedKeys]
 * @param {string[]} [allowedTypes]
 * @returns {{ ok: true, tab: object } | { ok: false, reason: string }}
 */
export function normalizePluginTab(
  tabConfig,
  reservedKeys = new Set(),
  allowedTypes = ['vue', 'extjs']
) {
  const validation = validatePluginTabConfig(tabConfig, reservedKeys, allowedTypes)
  if (!validation.ok) {
    return validation
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
    },
  }
}

/**
 * Replace an early plugin stub with a real registry and re-register its queued tabs.
 * Modules load after regular plugin scripts, so a stub may already hold `pendingTabs`.
 *
 * @param {string} globalKey
 * @param {new () => object} RegistryClass
 * @returns {object} the installed registry
 */
export function adoptPendingTabs(globalKey, RegistryClass) {
  const earlyPending = window[globalKey]?.pendingTabs || []
  const registry = new RegistryClass()
  window[globalKey] = registry
  earlyPending.forEach(tab => registry.register(tab))
  return registry
}

/** Product keeps the legacy `plugin-extjs` type used by older add-ons. */
const PRODUCT_TAB_TYPES = ['vue', 'extjs', 'plugin-extjs']

/**
 * @param {object} tabConfig
 */
export function validateProductPluginTabConfig(tabConfig) {
  return validatePluginTabConfig(tabConfig, RESERVED_PRODUCT_TAB_KEYS, PRODUCT_TAB_TYPES)
}

/**
 * @param {object} tabConfig
 */
export function normalizeProductPluginTab(tabConfig) {
  return normalizePluginTab(tabConfig, RESERVED_PRODUCT_TAB_KEYS, PRODUCT_TAB_TYPES)
}
