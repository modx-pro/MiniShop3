/**
 * Force MODX-aligned primary CSS variables after PrimeVue theme injection.
 *
 * PrimeVue is loaded from VueTools (Vite external). A preset built with the
 * bundled `@primeuix/themes` definePreset can fail to rewrite --p-emerald-* /
 * --p-primary-* in the Theme service that VueTools ships, leaving Aura's
 * #10b981 on :root. Late <style> forces MODX `#6cb24a` regardless.
 */
import { PRIMARY_500, PRIMARY_SCALE } from './ms3Preset.js'

const STYLE_ID = 'ms3-primary-theme-vars'

function buildCss() {
  const decls = []
  for (const [stop, hex] of Object.entries(PRIMARY_SCALE)) {
    decls.push(`--p-primary-${stop}:${hex}`)
    decls.push(`--p-emerald-${stop}:${hex}`)
    decls.push(`--p-green-${stop}:${hex}`)
  }
  decls.push(`--p-primary-color:${PRIMARY_500}`)
  decls.push(`--p-primary-hover-color:${PRIMARY_SCALE[600]}`)
  decls.push(`--p-primary-active-color:${PRIMARY_SCALE[700]}`)
  decls.push(`--p-primary-contrast-color:#ffffff`)
  decls.push(`--ms3-accent-primary:${PRIMARY_500}`)
  return `:root,:host{${decls.join(';')}}`
}

/**
 * Idempotent inject / refresh of primary theme CSS variables.
 */
export function injectMs3ThemeVars() {
  if (typeof document === 'undefined') {
    return
  }

  let el = document.getElementById(STYLE_ID)
  if (!el) {
    el = document.createElement('style')
    el.id = STYLE_ID
    document.head.appendChild(el)
  }
  el.textContent = buildCss()
}

/**
 * Inject now and once more after the current frame so we win over PrimeVue's
 * runtime theme stylesheet (same timing strategy as injectFormStylesOverride).
 */
export function scheduleMs3ThemeVars() {
  injectMs3ThemeVars()
  if (typeof requestAnimationFrame === 'function') {
    requestAnimationFrame(() => injectMs3ThemeVars())
  }
  if (typeof setTimeout === 'function') {
    setTimeout(() => injectMs3ThemeVars(), 0)
  }
}
