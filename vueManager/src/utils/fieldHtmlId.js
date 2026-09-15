/**
 * Build a stable, unique HTML id for a form field so `<label for>` stays
 * wired to its input across renders.
 *
 * @param {string} name Field name (from field config).
 * @param {string} [prefix='df'] Per-form prefix to avoid collisions between forms mounted on the same page.
 * @returns {string}
 */
export function fieldHtmlId(name, prefix = 'df') {
  return `${prefix}-field-${name}`
}
