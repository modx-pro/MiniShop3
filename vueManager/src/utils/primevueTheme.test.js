import { __setThemeNameForTests } from '@vuetools/useTheme'
import { beforeEach, describe, expect, it } from 'vitest'

import {
  getPrimarySaveSeverity,
  isModxManagerTheme,
  shouldInjectFormStylesOverride,
} from './primevueTheme.js'

describe('primevueTheme helpers (#701 Part B / #738)', () => {
  beforeEach(() => {
    __setThemeNameForTests('aura')
  })

  it('uses success severity only under Modx theme', () => {
    __setThemeNameForTests('modx')
    expect(isModxManagerTheme()).toBe(true)
    expect(getPrimarySaveSeverity()).toBe('success')
    expect(shouldInjectFormStylesOverride()).toBe(false)
  })

  it('leaves Aura Save on default primary (no success restyle)', () => {
    expect(isModxManagerTheme()).toBe(false)
    expect(getPrimarySaveSeverity()).toBeUndefined()
    expect(shouldInjectFormStylesOverride()).toBe(true)
  })
})
