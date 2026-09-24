import { __setThemeNameForTests } from '@vuetools/useTheme'
import { beforeEach, describe, expect, it } from 'vitest'

import { isModxManagerTheme, shouldInjectFormStylesOverride } from './primevueTheme.js'

describe('primevueTheme helpers (#701 Part B / #738)', () => {
  beforeEach(() => {
    __setThemeNameForTests('aura')
  })

  it('skips Aura-era form density under Modx theme', () => {
    __setThemeNameForTests('modx')
    expect(isModxManagerTheme()).toBe(true)
    expect(shouldInjectFormStylesOverride()).toBe(false)
  })

  it('keeps Aura form density override', () => {
    expect(isModxManagerTheme()).toBe(false)
    expect(shouldInjectFormStylesOverride()).toBe(true)
  })
})
