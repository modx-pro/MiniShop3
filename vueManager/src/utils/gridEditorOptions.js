/**
 * Pure helpers for category-products grid inline combo/select (no HTTP, no Vue).
 */

const REFERENCES_PATH_PREFIX = '/api/mgr/references/'

/**
 * @param {unknown} url
 * @returns {boolean}
 */
export function isAllowlistedComboEndpoint(url) {
  const t = String(url ?? '').trim()
  if (!t || t[0] !== '/') {
    return false
  }
  const path = t.split('?')[0]
  return path.startsWith(REFERENCES_PATH_PREFIX)
}

/**
 * Resolve GET URL for combo options. Override wins if allowlisted; else registry path by key.
 *
 * @param {{ editor_reference?: string, editor_combo_endpoint?: string }} column
 * @param {Record<string, string>} pathByReferenceKey from API `editor_references`
 * @returns {string|null}
 */
export function resolveComboRequestUrl(column, pathByReferenceKey = {}) {
  const override = String(column?.editor_combo_endpoint ?? '').trim()
  const ref = String(column?.editor_reference ?? '').trim()
  const map = pathByReferenceKey && typeof pathByReferenceKey === 'object' ? pathByReferenceKey : {}

  if (override && isAllowlistedComboEndpoint(override)) {
    return override
  }
  if (ref && map[ref]) {
    return map[ref]
  }
  return null
}

/**
 * @param {unknown} item
 * @returns {{ value: unknown, label: string }|null}
 */
function normalizeOptionRow(item) {
  if (item == null || typeof item !== 'object') {
    return null
  }
  const hasValue = 'value' in item && item.value !== undefined && item.value !== null
  const hasId = 'id' in item && item.id !== undefined && item.id !== null
  const value = hasValue ? item.value : hasId ? item.id : null
  if (value === null || value === undefined) {
    return null
  }
  const label = item.label ?? item.name ?? String(value)
  return { value, label: String(label) }
}

/**
 * Map references API JSON to PrimeVue Select options `{ value, label }[]`.
 * Prefers canonical `options`; falls back to `vendors` / `data` for BC.
 *
 * @param {unknown} data Parsed JSON body
 * @returns {{ value: unknown, label: string }[]}
 */
export function toSelectOptionsFromReferencesResponse(data) {
  if (!data || typeof data !== 'object') {
    return []
  }
  if (Array.isArray(data.options) && data.options.length > 0) {
    const mapped = data.options.map(normalizeOptionRow).filter(Boolean)
    if (mapped.length > 0) {
      return mapped
    }
  }
  const rawList = data.vendors ?? data.data ?? []
  if (!Array.isArray(rawList)) {
    return []
  }
  return rawList.map(normalizeOptionRow).filter(Boolean)
}
