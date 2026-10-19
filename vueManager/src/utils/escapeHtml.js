/**
 * Escape text for insertion into an HTML template (v-html).
 *
 * @param {unknown} value
 * @returns {string}
 */
export function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

/**
 * Replace {field} tokens. Values are escaped. The template markup is left as written.
 *
 * @param {string} template
 * @param {Record<string, unknown>|null|undefined} data
 * @returns {string}
 */
export function renderTemplate(template, data) {
  return String(template ?? '').replace(/\{(\w+)\}/g, (match, key) => {
    const value = data?.[key]
    if (value === undefined || value === null) {
      return ''
    }
    return escapeHtml(value)
  })
}
