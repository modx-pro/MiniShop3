/**
 * MiniShop3 PrimeVue preset — Aura with MODX-aligned primary/success green (#4e8136 @ 500).
 *
 * Aura maps `semantic.primary` → `{emerald.*}`. Remap `emerald` (and `green` for
 * severity="success") so --p-primary-* and success chrome share one AA-safe scale.
 */
import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

/** Primary scale 50–950; 500 matches MODX manager green (WCAG AA with white text). */
export const PRIMARY_SCALE = {
  50: '#f1f6ee',
  100: '#dce8d3',
  200: '#bdd1ad',
  300: '#98b681',
  400: '#6f974f',
  500: '#4e8136',
  600: '#3d6b2b',
  700: '#325324',
  800: '#284221',
  900: '#1f331c',
  950: '#0f1c0d',
}

/** Anchor color, exported for tests and CSS-fallback consumers. */
export const PRIMARY_500 = PRIMARY_SCALE[500]

export const ms3Preset = definePreset(Aura, {
  primitive: {
    emerald: PRIMARY_SCALE,
    green: PRIMARY_SCALE,
  },
})
