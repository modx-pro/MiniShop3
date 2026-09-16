/**
 * Manager PrimeVue theme options.
 *
 * VueTools `useTheme` resolves the active theme from the `vuetools.theme` system
 * setting (registry: `aura` → `{ theme: { preset: Aura } }`, `modx` →
 * `{ theme: ModxManagerTheme }` where `ModxManagerTheme = { preset, options }`).
 *
 * The manager chrome stays light even when the OS prefers dark, so
 * `darkModeSelector: 'none'` is forced on top of the registry defaults.
 * Spreading `active.theme` keeps both shapes intact: aura's `{ preset }` and
 * modx's `{ preset, options }`.
 */
import { getActiveTheme } from '@vuetools/useTheme'

export function getManagerPrimeVueThemeOptions() {
  const active = getActiveTheme()
  return {
    ...active,
    theme: {
      ...active.theme,
      options: {
        ...(active.theme?.options ?? {}),
        darkModeSelector: 'none',
      },
    },
  }
}
