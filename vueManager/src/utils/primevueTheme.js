/**
 * PrimeVue theme for MiniShop3 manager entries.
 *
 * Resolves `vuetools.theme` via VueTools and forces manager chrome options:
 * - `darkModeSelector: 'none'` — stay light even when the OS prefers dark
 * - `cssLayer: false` — keep cascade against MODX/Ext manager CSS (was on
 *   main.js / category-options before getActiveTheme migration)
 *
 * Registry shapes: aura `{ theme: { preset } }`, modx `{ theme: ModxManagerTheme }`
 * where ModxManagerTheme = `{ preset, options }`.
 */
import { getPrimeVueLocale } from '@vuetools/usePrimeVueLocale'
import { getActiveTheme, getThemeName } from '@vuetools/useTheme'

function getManagerPrimeVueThemeOptions() {
  const { theme, ...rest } = getActiveTheme()
  return {
    ...rest,
    theme: {
      ...theme,
      options: {
        ...theme?.options,
        darkModeSelector: 'none',
        cssLayer: false,
      },
    },
  }
}

/**
 * Theme options plus manager locale.
 * @param {{ pt?: object }} [options]
 */
export function getManagerPrimeVueConfig(options = {}) {
  const config = {
    ...getManagerPrimeVueThemeOptions(),
    locale: getPrimeVueLocale(),
  }
  if (options.pt !== undefined) {
    config.pt = options.pt
  }
  return config
}

export function isModxManagerTheme() {
  return getThemeName() === 'modx'
}

/** Aura-era form density inject should not run under Modx preset. */
export function shouldInjectFormStylesOverride() {
  return !isModxManagerTheme()
}

/**
 * Severity for primary Save (and order Create) actions.
 * Modx uses success green; Aura keeps default primary so Part B does not
 * restyle the default theme (#701 / #738 review).
 *
 * @returns {'success'|undefined}
 */
export function getPrimarySaveSeverity() {
  return isModxManagerTheme() ? 'success' : undefined
}
