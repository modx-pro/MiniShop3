import { describe, expect, it } from 'vitest'

import { PRIMARY_500, PRIMARY_SCALE } from './ms3Preset.js'

/** sRGB channel → linear light, per WCAG 2.x. */
function channelToLinear(c) {
  const s = c / 255
  return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
}

function relativeLuminance(hex) {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim())
  if (!m) throw new Error(`bad hex: ${hex}`)
  const r = parseInt(m[1].slice(0, 2), 16)
  const g = parseInt(m[1].slice(2, 4), 16)
  const b = parseInt(m[1].slice(4, 6), 16)
  return (
    0.2126 * channelToLinear(r) +
    0.7152 * channelToLinear(g) +
    0.0722 * channelToLinear(b)
  )
}

describe('ms3Preset primary scale', () => {
  it('exposes a full 50–950 scale', () => {
    for (const stop of [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]) {
      expect(PRIMARY_SCALE[stop]).toMatch(/^#[0-9a-f]{6}$/i)
    }
  })

  it('500 anchor matches MODX ExtJS primary-button', () => {
    expect(PRIMARY_500.toLowerCase()).toBe('#6cb24a')
    expect(PRIMARY_SCALE[600].toLowerCase()).toBe('#528738')
  })

  it('scale is monotonic (light → dark)', () => {
    const stops = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]
    const lum = stops.map(s => relativeLuminance(PRIMARY_SCALE[s]))
    for (let i = 1; i < lum.length; i++) {
      expect(lum[i]).toBeLessThan(lum[i - 1])
    }
  })
})
