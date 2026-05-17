/**
 * Label kind for delivery/payment «additional cost» string (discount via negative literal or numeric value).
 *
 * @param {unknown} value InputText v-model value
 * @returns {'discount' | 'markup' | null}
 */
export function resolveAddCostPriceBadgeKind(value) {
  if (value === null || value === undefined) {
    return null
  }
  const trimmed = String(value).trim()
  if (trimmed === '' || trimmed === '0') {
    return null
  }

  const withoutPercentSuffix = trimmed.replace(/%$/u, '')
  const forParse = withoutPercentSuffix.replace(',', '.').trim()
  const numeric = Number.parseFloat(forParse)
  if (Number.isFinite(numeric)) {
    if (numeric < 0) return 'discount'
    if (numeric > 0) return 'markup'
    return null
  }

  if (/^-/.test(trimmed)) return 'discount'
  if (/^\+/.test(trimmed)) return 'markup'

  return null
}
