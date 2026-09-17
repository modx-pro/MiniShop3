import { describe, expect, it } from 'vitest'

import { RESERVED_ORDER_TAB_KEYS, validateOrderPluginTabConfig } from './orderPluginTab.js'

describe('orderPluginTab', () => {
  it('reserves the built-in ms3_shipment tab key, not tracking', () => {
    expect(RESERVED_ORDER_TAB_KEYS.has('ms3_shipment')).toBe(true)
    expect(RESERVED_ORDER_TAB_KEYS.has('tracking')).toBe(false)

    const reserved = validateOrderPluginTabConfig({
      key: 'ms3_shipment',
      title: 'Shipment',
      type: 'vue',
      component: {},
    })
    expect(reserved.ok).toBe(false)

    // Docs historically used key: tracking for plugin examples — keep it free.
    const docsExample = validateOrderPluginTabConfig({
      key: 'tracking',
      title: 'Tracking',
      type: 'vue',
      component: {},
    })
    expect(docsExample.ok).toBe(true)
  })
})
