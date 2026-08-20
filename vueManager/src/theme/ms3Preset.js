/**
 * MiniShop3 PrimeVue preset — Aura with MODX manager green (#6cb24a @ 500).
 *
 * Aura maps `semantic.primary` → `{emerald.*}`. Remap `emerald` (and `green` for
 * severity="success") so Vue chrome matches ExtJS `.primary-button` (#6CB24A).
 * White-on-fill contrast matches MODX core (not WCAG AA); personality > darkening.
 */
import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

/**
 * Primary scale 50–950.
 * 500 = MODX manager `#6CB24A`; 600 = MODX hover `#528738` (login / primary-button).
 */
export const PRIMARY_SCALE = {
  50: '#f4faf0',
  100: '#e4f3db',
  200: '#c9e7b8',
  300: '#a5d68c',
  400: '#84c466',
  500: '#6cb24a',
  600: '#528738',
  700: '#426c2d',
  800: '#365625',
  900: '#2d4720',
  950: '#172612',
}

/** Anchor color, exported for tests and CSS-fallback consumers. */
export const PRIMARY_500 = PRIMARY_SCALE[500]

/** MODX ExtJS `.primary-button` fill — keep in sync with manager index.css. */
export const MODX_PRIMARY_BUTTON = '#6cb24a'

export const ms3Preset = definePreset(Aura, {
  primitive: {
    emerald: PRIMARY_SCALE,
    green: PRIMARY_SCALE,
  },
})
