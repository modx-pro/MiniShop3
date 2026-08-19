import { describe, expect, it } from 'vitest'

import { RESERVED_ORDER_TAB_KEYS, validateOrderPluginTabConfig } from './orderPluginTab.js'

describe('orderPluginTab', () => {
  it('reserves the built-in tracking tab key', () => {
    expect(RESERVED_ORDER_TAB_KEYS.has('tracking')).toBe(true)
    const result = validateOrderPluginTabConfig({
      key: 'tracking',
      title: 'Tracking',
      type: 'vue',
      component: {},
    })
    expect(result.ok).toBe(false)
  })
})
