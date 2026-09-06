/**
 * Group visible product-data fields into sections ordered by section.sort_order.
 *
 * `getPageFields` returns sections as an object keyed by numeric id. A plain
 * object + `v-for` enumerates integer-like keys in ascending id order, which
 * ignores the Utilities → Product fields section sort (#611).
 *
 * @param {Array<Object>} fields Visible field configs (already filtered).
 * @param {Object<string|number, Object>} sectionsById Map from section id → section config.
 * @returns {Array<{ key: string|number, fields: Array<Object>, [string]: any }>}
 */
export function groupProductDataSections(fields, sectionsById = {}) {
  const grouped = new Map()

  for (const field of fields) {
    // Normalize to string so Map keys match JSON object keys from getPageFields.
    const sectionKey = field.section == null || field.section === '' ? 'default' : String(field.section)
    const sectionConfig = sectionsById[sectionKey]

    if (sectionConfig?.hidden === true) {
      continue
    }

    if (!grouped.has(sectionKey)) {
      grouped.set(sectionKey, {
        // Fallback identity when section is missing from the map; API `key` (section_key) wins via spread.
        key: sectionKey,
        ...(sectionConfig || {}),
        fields: [],
      })
    }

    grouped.get(sectionKey).fields.push(field)
  }

  const sections = Array.from(grouped.values())

  sections.sort((a, b) => {
    const sortA = Number.isFinite(Number(a.sort_order)) ? Number(a.sort_order) : Number.MAX_SAFE_INTEGER
    const sortB = Number.isFinite(Number(b.sort_order)) ? Number(b.sort_order) : Number.MAX_SAFE_INTEGER
    if (sortA !== sortB) {
      return sortA - sortB
    }

    const idA = Number(a.id ?? a.key)
    const idB = Number(b.id ?? b.key)
    if (Number.isFinite(idA) && Number.isFinite(idB) && idA !== idB) {
      return idA - idB
    }

    return String(a.id ?? a.key).localeCompare(String(b.id ?? b.key))
  })

  return sections
}
