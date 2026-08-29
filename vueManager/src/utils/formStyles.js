/**
 * @deprecated Control density (Normal = 36px) and form font sizes live in
 * VueTools `ModxManagerTheme` (`modx.control.height`, preset CSS). Kept as a
 * no-op so existing entry `injectFormStylesOverride()` calls stay harmless.
 */
export function injectFormStylesOverride() {
  // no-op — see VueTools src/theme/modx/
}
