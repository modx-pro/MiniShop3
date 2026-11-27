import { beforeEach, describe, expect, it } from 'vitest'

import {
  adoptPendingTabs,
  normalizeProductPluginTab,
  RESERVED_PRODUCT_TAB_KEYS,
  snapshotPluginTabConfigForQueue,
  validateProductPluginTabConfig,
} from './pluginTab.js'

describe('pluginTab', () => {
  beforeEach(() => {
    delete window.MS3ProductTabsRegistry
  })

  it('replaces an early stub and re-registers its queue (#852)', () => {
    class ProductTabsRegistry {
      constructor() {
        this.pendingTabs = []
      }

      register(tab) {
        this.pendingTabs.push(tab)
        return true
      }
    }

    const stub = {
      pendingTabs: [{ key: 'variants', title: 'Variants', type: 'vue', component: 'Variants' }],
      register(tab) {
        this.pendingTabs.push(tab)
        return true
      },
    }
    window.MS3ProductTabsRegistry = stub

    const registry = adoptPendingTabs('MS3ProductTabsRegistry', ProductTabsRegistry)

    expect(window.MS3ProductTabsRegistry).toBe(registry)
    expect(registry).toBeInstanceOf(ProductTabsRegistry)
    expect(registry.pendingTabs).toEqual(stub.pendingTabs)
    expect(typeof registry.register).toBe('function')
  })

  it('does not lose the queue when no stub exists', () => {
    class Registry {
      constructor() {
        this.pendingTabs = []
      }

      register(tab) {
        this.pendingTabs.push(tab)
        return true
      }
    }

    const registry = adoptPendingTabs('MS3ProductTabsRegistry', Registry)
    expect(registry.pendingTabs).toEqual([])
  })

  it('rejects product built-in keys and invalid tab types', () => {
    for (const key of RESERVED_PRODUCT_TAB_KEYS) {
      const result = validateProductPluginTabConfig({
        key,
        title: 'Mine',
        type: 'vue',
        component: 'Mine',
      })
      expect(result.ok, key).toBe(false)
    }

    expect(
      validateProductPluginTabConfig({ key: 'mine', title: 'Mine', type: 'vue', component: 'Mine' })
        .ok
    ).toBe(true)
    expect(
      validateProductPluginTabConfig({
        key: 'mine',
        title: 'Mine',
        type: 'flash',
        component: 'Mine',
      }).ok
    ).toBe(false)
    expect(validateProductPluginTabConfig({ key: 'mine', title: 'Mine', type: 'vue' }).ok).toBe(
      false
    )
    expect(validateProductPluginTabConfig({ key: 'mine', title: 'Mine', type: 'extjs' }).ok).toBe(
      false
    )
    expect(
      validateProductPluginTabConfig({ key: 'mine', title: 'Mine', type: 'extjs', xtype: 'mine' })
        .ok
    ).toBe(true)
    // Legacy product type stays accepted for existing add-ons.
    expect(
      validateProductPluginTabConfig({
        key: 'mine',
        title: 'Mine',
        type: 'plugin-extjs',
        xtype: 'mine',
      }).ok
    ).toBe(true)
  })

  it('normalizes product tabs and snapshots nested config for the queue', () => {
    const result = normalizeProductPluginTab({
      key: 'variants',
      title: 'Variants',
      component: 'Variants',
      extConfig: { a: 1 },
      props: { b: 2 },
    })
    expect(result.ok).toBe(true)
    expect(result.tab).toMatchObject({
      key: 'variants',
      title: 'Variants',
      type: 'vue',
      position: 100,
    })

    const original = {
      key: 'v',
      title: 'V',
      component: 'V',
      props: { deep: 1 },
      extConfig: { deep: 1 },
    }
    const snapshot = snapshotPluginTabConfigForQueue(original)
    original.props.deep = 2
    original.extConfig.deep = 2
    expect(snapshot.props.deep).toBe(1)
    expect(snapshot.extConfig.deep).toBe(1)
  })

  it('runs early tabs through the real register so validation applies', () => {
    class Registry {
      constructor() {
        this.accepted = []
      }

      register(tab) {
        if (!validateProductPluginTabConfig(tab).ok) {
          return false
        }
        this.accepted.push(tab)
        return true
      }
    }

    window.MS3ProductTabsRegistry = {
      pendingTabs: [{ key: 'gallery', title: 'Mine', type: 'vue', component: 'Mine' }],
      register() {
        return true
      },
    }

    const registry = adoptPendingTabs('MS3ProductTabsRegistry', Registry)
    expect(registry.accepted).toEqual([])
  })
})
