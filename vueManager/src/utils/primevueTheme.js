/**
 * PrimeVue theme for MiniShop3 manager entries.
 *
 * Resolves `vuetools.theme` via VueTools and forces `darkModeSelector: 'none'`
 * so manager chrome stays light (aura `{ preset }` or modx `{ preset, options }`).
 */
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale'
import { getActiveTheme, getThemeName } from '@vuetools/useTheme'

export function isModxManagerTheme() {
  return getThemeName() === 'modx'
}

export function getManagerPrimeVueThemeOptions() {
  const { theme, ...rest } = getActiveTheme()
  return {
    ...rest,
    theme: {
      ...theme,
      options: {
        ...theme?.options,
        darkModeSelector: 'none',
      },
    },
  }
}

/** Theme options plus manager locale; pass PrimeVue extras (e.g. `pt`) when needed. */
export function getManagerPrimeVueConfig(extra = {}) {
  return {
    ...getManagerPrimeVueThemeOptions(),
    locale: getPrimeVueLocale(),
    ...extra,
  }
}
