import { nextTick, ref } from 'vue'

import {
  GridColumnEditorType,
  isComboEditorType,
  isSelectLikeEditorType,
  normalizeGridColumnEditorType,
} from '../constants/gridColumnEditorTypes.js'
import {
  resolveComboRequestUrl,
  toSelectOptionsFromReferencesResponse,
} from '../utils/gridEditorOptions.js'

/**
 * Inline edit state and actions for category products grid.
 *
 * @param {Object} deps
 * @param {import('vue').Ref} deps.products
 * @param {import('vue').Ref<Record<string, string>>} deps.referencePathsByKey Paths from GET grid-config (editor_references)
 * @param {Function} deps.request HTTP client (e.g. project request)
 * @param {{ add: Function }} deps.toast PrimeVue toast
 * @param {Function} deps._ Lexicon helper
 */
export function useCategoryProductsInlineEdit(deps) {
  const { products, referencePathsByKey, request, toast, _ } = deps

  const editingCell = ref(null)
  const inlineEditValue = ref('')
  const inlineEditSaving = ref(false)
  const inlineEditInputRef = ref(null)
  const comboOptions = ref([])

  function isBooleanColumn(column) {
    return column.type === 'boolean'
  }

  function isEditingCell(product, column) {
    return (
      editingCell.value &&
      editingCell.value.productId === product.id &&
      editingCell.value.columnName === column.name
    )
  }

  function normalizeValueForSave(rawValue, column) {
    if (isBooleanColumn(column)) {
      return rawValue ? 1 : 0
    }
    const editorType = normalizeGridColumnEditorType(column.editor_type)
    if (editorType === GridColumnEditorType.NUMBER) {
      if (rawValue === '' || rawValue === null) {
        return null
      }
      const num = Number(rawValue)
      return Number.isNaN(num) ? null : num
    }
    if (isSelectLikeEditorType(editorType)) {
      return rawValue
    }
    return rawValue
  }

  function isInlineValueUnchanged(original, value, column) {
    if (isBooleanColumn(column)) {
      return (original ? 1 : 0) === value
    }
    const editorType = normalizeGridColumnEditorType(column.editor_type)
    if (editorType === GridColumnEditorType.NUMBER) {
      const norm = v =>
        v === null || v === undefined || v === '' || Number.isNaN(Number(v)) ? null : Number(v)
      return norm(original) === norm(value)
    }
    if (isSelectLikeEditorType(editorType)) {
      const o = original === null || original === undefined ? null : original
      const v = value === null || value === undefined ? null : value
      return o === v || String(o) === String(v)
    }
    const origStr = original === null || original === undefined ? '' : String(original)
    const valStr = value === null || value === undefined ? '' : String(value)
    return origStr === valStr
  }

  function clearInlineEditState() {
    editingCell.value = null
    inlineEditValue.value = ''
    comboOptions.value = []
  }

  async function loadComboOptions(column) {
    const url = resolveComboRequestUrl(column, referencePathsByKey?.value ?? {})
    if (!url) {
      comboOptions.value = []
      return
    }
    try {
      const response = await request.get(url)
      comboOptions.value = toSelectOptionsFromReferencesResponse(response)
    } catch (err) {
      if (import.meta.env.DEV) {
        console.error('[useCategoryProductsInlineEdit] Combo options load failed:', err)
      }
      comboOptions.value = []
      toast.add({
        severity: 'warn',
        summary: _('error'),
        detail: _('combo_options_load_failed') || err.message,
        life: 3000,
      })
    }
  }

  async function startInlineEdit(product, column) {
    if (!column.editable) {
      return
    }
    if (inlineEditSaving.value) {
      return
    }
    editingCell.value = { productId: product.id, columnName: column.name }
    const raw = product[column.name]
    inlineEditValue.value = raw === null || raw === undefined ? '' : raw
    if (isComboEditorType(column.editor_type)) {
      await loadComboOptions(column)
    }
    nextTick(() => {
      const comp = inlineEditInputRef.value
      if (!comp) {
        return
      }
      const el =
        comp.$el?.querySelector?.('input') ??
        comp.$el?.querySelector?.('.p-dropdown') ??
        comp.$el ??
        comp
      if (el?.focus) {
        el.focus()
      }
    })
  }

  async function saveInlineEdit(product, column) {
    if (
      !editingCell.value ||
      editingCell.value.productId !== product.id ||
      editingCell.value.columnName !== column.name
    ) {
      return
    }
    if (inlineEditSaving.value) {
      return
    }
    const value = normalizeValueForSave(inlineEditValue.value, column)
    if (isInlineValueUnchanged(product[column.name], value, column)) {
      clearInlineEditState()
      return
    }
    inlineEditSaving.value = true
    try {
      const res = await request.put(`/api/mgr/product-data/${product.id}`, { [column.name]: value })
      const idx = products.value.findIndex(p => p.id === product.id)
      if (idx >= 0) {
        if (res && typeof res === 'object') {
          products.value[idx] = { ...products.value[idx], ...res }
        } else {
          products.value[idx] = { ...products.value[idx], [column.name]: value }
        }
      }
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('inline_edit_saved'),
        life: 2000,
      })
    } catch (error) {
      if (import.meta.env.DEV) {
        console.error('[useCategoryProductsInlineEdit] Inline edit save failed:', error)
      }
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('inline_edit_error'),
        life: 5000,
      })
      return
    } finally {
      inlineEditSaving.value = false
    }
    clearInlineEditState()
  }

  function cancelInlineEdit() {
    clearInlineEditState()
  }

  /** Options for unified Select: static select uses column.editor_options; combo uses loaded list. */
  function selectOptionsForColumn(column) {
    if (normalizeGridColumnEditorType(column.editor_type) === GridColumnEditorType.SELECT) {
      return Array.isArray(column.editor_options) ? column.editor_options : []
    }
    if (isComboEditorType(column.editor_type)) {
      return comboOptions.value
    }
    return []
  }

  function selectUsesClear(column) {
    return isComboEditorType(column.editor_type)
  }

  return {
    editingCell,
    inlineEditValue,
    inlineEditSaving,
    inlineEditInputRef,
    comboOptions,
    isBooleanColumn,
    isEditingCell,
    normalizeValueForSave,
    isInlineValueUnchanged,
    startInlineEdit,
    saveInlineEdit,
    cancelInlineEdit,
    selectOptionsForColumn,
    selectUsesClear,
  }
}
