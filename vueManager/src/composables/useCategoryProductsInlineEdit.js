import { nextTick, onUnmounted, ref } from 'vue'

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
 * @param {import('vue').Ref<number>|number} deps.categoryId Category context for scoped inline edits
 * @param {import('vue').Ref<boolean>|boolean} deps.nested Include nested subcategories in scope
 * @param {Function} deps.request HTTP client (e.g. project request)
 * @param {{ add: Function }} deps.toast PrimeVue toast
 * @param {Function} deps._ Lexicon helper
 */
export function useCategoryProductsInlineEdit(deps) {
  const { products, referencePathsByKey, categoryId, nested, request, toast, _ } = deps

  const resolveCategoryId = () =>
    typeof categoryId === 'object' && categoryId !== null ? categoryId.value : categoryId

  const resolveNested = () =>
    Boolean(typeof nested === 'object' && nested !== null ? nested.value : nested)

  const editingCell = ref(null)
  const inlineEditValue = ref('')
  const inlineEditSaving = ref(false)
  const inlineEditInputRef = ref(null)
  const comboOptions = ref([])
  const currentEditorType = ref(null)

  function isBooleanColumn(column) {
    return column.type === 'boolean'
  }

  /**
   * Coerce raw value to match the type of the editor options.
   * Fixes PrimeVue Select strict-comparison issue when editor_options uses
   * different type than stored data (e.g. value:1/0 vs true/false).
   */
  function coerceValueToOptionType(raw, options) {
    if (raw === '' || raw === null || raw === undefined) {
      return raw
    }
    if (!Array.isArray(options) || options.length === 0) {
      return raw
    }
    const sample = options[0]?.value
    if (sample === undefined || sample === null) {
      return raw
    }
    const sampleType = typeof sample
    const rawType = typeof raw
    if (sampleType === rawType) {
      return raw
    }
    if (sampleType === 'number' && rawType === 'boolean') {
      return raw ? 1 : 0
    }
    if (sampleType === 'boolean' && rawType === 'number') {
      return raw === 1 || raw === true
    }
    if (sampleType === 'string') {
      return String(raw)
    }
    if (sampleType === 'number' && rawType === 'string') {
      const n = Number(raw)
      return Number.isNaN(n) ? raw : n
    }
    return raw
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
    currentEditorType.value = null
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
    currentEditorType.value = normalizeGridColumnEditorType(column.editor_type)
    const raw = product[column.name]
    let initial = raw === null || raw === undefined ? '' : raw
    if (
      normalizeGridColumnEditorType(column.editor_type) === GridColumnEditorType.SELECT
      && Array.isArray(column.editor_options)
    ) {
      initial = coerceValueToOptionType(initial, column.editor_options)
    }
    inlineEditValue.value = initial
    if (isComboEditorType(column.editor_type)) {
      await loadComboOptions(column)
      inlineEditValue.value = coerceValueToOptionType(inlineEditValue.value, comboOptions.value)
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
      const scopedCategoryId = resolveCategoryId()
      const payload = { [column.name]: value }
      if (resolveNested()) {
        payload.nested = 1
      }
      const res = await request.put(
        `/api/mgr/categories/${scopedCategoryId}/products/${product.id}/data`,
        payload
      )
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

  /**
   * Global dismiss handlers: ESC and click outside the active editing cell.
   *
   * ESC at element-level doesn't work for PrimeVue Select (it captures the event
   * for closing its own dropdown). Document-level handler catches it reliably.
   *
   * Click-outside is needed because there's no mouse way to dismiss otherwise —
   * only saving or activating another cell, both have side effects.
   *
   * PrimeVue Select renders its dropdown via teleport (.p-select-overlay), so
   * clicks on options must NOT trigger dismiss.
   */
  function handleGlobalKeydown(event) {
    if (event.key === 'Escape' && editingCell.value && !inlineEditSaving.value) {
      cancelInlineEdit()
    }
  }

  function handleGlobalClick(event) {
    if (!editingCell.value || inlineEditSaving.value) {
      return
    }
    const target = event.target
    if (!(target instanceof Element)) {
      return
    }
    if (target.closest('.inline-edit-cell')) {
      return
    }
    if (target.closest('.p-select-overlay, .p-overlay, .p-component-overlay')) {
      return
    }
    // Use `click` (not `mousedown`) so @blur on text/number inputs fires first
    // and triggers saveInlineEdit. If save happened, editingCell is already
    // null and cancelInlineEdit is a no-op. For Select/Checkbox (no @blur),
    // this acts as primary dismiss.
    cancelInlineEdit()
  }

  document.addEventListener('keydown', handleGlobalKeydown)
  document.addEventListener('click', handleGlobalClick)

  onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeydown)
    document.removeEventListener('click', handleGlobalClick)
  })

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
