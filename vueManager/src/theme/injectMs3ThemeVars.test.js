import { afterEach, describe, expect, it } from 'vitest'

import { injectMs3ThemeVars } from './injectMs3ThemeVars.js'
import { PRIMARY_500, PRIMARY_SCALE } from './ms3Preset.js'

afterEach(() => {
  document.getElementById('ms3-primary-theme-vars')?.remove()
})

describe('injectMs3ThemeVars', () => {
  it('writes primary/emerald/green CSS variables onto a style tag', () => {
    injectMs3ThemeVars()

    const el = document.getElementById('ms3-primary-theme-vars')
    expect(el).toBeTruthy()
    expect(el.textContent).toContain(`--p-primary-500:${PRIMARY_500}`)
    expect(el.textContent).toContain(`--p-emerald-500:${PRIMARY_500}`)
    expect(el.textContent).toContain(`--p-green-500:${PRIMARY_SCALE[500]}`)
    expect(el.textContent).toContain(`--ms3-accent-primary:${PRIMARY_500}`)
  })

  it('is idempotent (updates the same style node)', () => {
    injectMs3ThemeVars()
    injectMs3ThemeVars()
    expect(document.querySelectorAll('#ms3-primary-theme-vars')).toHaveLength(1)
  })
})
