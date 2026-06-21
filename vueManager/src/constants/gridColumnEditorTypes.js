/**
 * Values stored in category-products grid column config as `editor_type`.
 * @typedef {'text'|'number'|'select'|'combo'} GridColumnEditorTypeValue
 */

export const GridColumnEditorType = Object.freeze({
  TEXT: 'text',
  NUMBER: 'number',
  SELECT: 'select',
  COMBO: 'combo',
})

const KNOWN_TYPES = new Set(Object.values(GridColumnEditorType))

/**
 * @param {unknown} type
 * @returns {GridColumnEditorTypeValue}
 */
export function normalizeGridColumnEditorType(type) {
  return typeof type === 'string' && KNOWN_TYPES.has(type) ? type : GridColumnEditorType.TEXT
}

/**
 * @param {unknown} type
 */
export function isSelectLikeEditorType(type) {
  const t = normalizeGridColumnEditorType(type)
  return t === GridColumnEditorType.SELECT || t === GridColumnEditorType.COMBO
}

/**
 * @param {unknown} type
 */
export function isComboEditorType(type) {
  return normalizeGridColumnEditorType(type) === GridColumnEditorType.COMBO
}
