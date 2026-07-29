/**
 * Product options table/json editing state for the edit-product dialog.
 *
 * @param {Object} deps
 * @param {Function} deps._
 * @param {import('vue').Ref} deps.editingProduct
 */
import { ref } from 'vue'

import request from '../request.js'

export function useOrderProductOptions(deps) {
  const { _, editingProduct } = deps

  const optionsEditMode = ref('table') // 'table' or 'json'
  const optionsTableData = ref([]) // Array of { type, key, value, isComplex, fieldValues, loadingValues }
  const optionsJsonText = ref('') // JSON string for json mode
  const optionsJsonError = ref('') // JSON parse error message

  const productOptionFields = ref([])
  const loadingOptionFields = ref(false)

  /**
   * Load product option fields (color, size, etc.)
   */
  async function loadProductOptionFields() {
    loadingOptionFields.value = true
    try {
      const response = await request.get('/api/mgr/references/product-option-fields')
      productOptionFields.value = response.fields || []
    } catch (error) {
      console.error('[OrderView] Error loading product option fields:', error)
      productOptionFields.value = []
    } finally {
      loadingOptionFields.value = false
    }
  }

  /**
   * Check if value is complex (array or object)
   */
  function isComplexValue(value) {
    return Array.isArray(value) || (typeof value === 'object' && value !== null)
  }

  /**
   * Load field values for a specific row
   */
  async function loadFieldValuesForRow(row) {
    if (!row.key || row.type !== 'field') return

    const productId = editingProduct.value?.product_id
    if (!productId) {
      console.warn('[OrderView] No product_id found for loading field values')
      row.fieldValues = []
      return
    }

    row.loadingValues = true
    try {
      const response = await request.get('/api/mgr/references/product-field-values', {
        field: row.key,
        product_id: productId,
      })
      row.fieldValues = response.values || []
    } catch (error) {
      console.error('[OrderView] Error loading field values:', error)
      row.fieldValues = []
    } finally {
      row.loadingValues = false
    }
  }

  /**
   * Initialize options data from product
   */
  async function initOptionsFromProduct(options) {
    let parsed = {}

    if (options) {
      if (typeof options === 'string') {
        try {
          parsed = JSON.parse(options)
        } catch {
          parsed = {}
        }
      } else if (typeof options === 'object') {
        parsed = options
      }
    }

    const tableData = []
    const fieldRowsToLoad = []
    for (const [key, value] of Object.entries(parsed)) {
      const isProductField = productOptionFields.value.some(f => f.name === key)
      const row = {
        type: isProductField ? 'field' : 'custom',
        key,
        value: isComplexValue(value) ? JSON.stringify(value, null, 2) : value,
        isComplex: isComplexValue(value),
        fieldValues: [],
        loadingValues: false,
      }

      if (isProductField) {
        fieldRowsToLoad.push(row)
      }

      tableData.push(row)
    }

    await Promise.all(fieldRowsToLoad.map(row => loadFieldValuesForRow(row)))

    optionsTableData.value = tableData
    optionsJsonText.value = Object.keys(parsed).length > 0 ? JSON.stringify(parsed, null, 2) : ''
  }

  /**
   * Handle option type change (field/custom)
   */
  async function onOptionTypeChange(row) {
    if (row.type === 'field') {
      const isValidField = productOptionFields.value.some(f => f.name === row.key)
      if (!isValidField && productOptionFields.value.length > 0) {
        row.key = productOptionFields.value[0].name
      }
      row.value = ''
      row.isComplex = false
      await loadFieldValuesForRow(row)
    } else {
      row.fieldValues = []
      row.value = ''
      row.isComplex = false
    }
    syncTableToJson()
  }

  /**
   * Handle field selection change
   */
  async function onFieldKeyChange(row) {
    row.value = ''
    row.fieldValues = []
    await loadFieldValuesForRow(row)
    syncTableToJson()
  }

  /**
   * Add new option row
   */
  function addOptionRow() {
    optionsTableData.value.push({
      type: 'custom',
      key: '',
      value: '',
      isComplex: false,
      fieldValues: [],
      loadingValues: false,
    })
  }

  /**
   * Remove option row
   */
  function removeOptionRow(index) {
    optionsTableData.value.splice(index, 1)
    syncTableToJson()
  }

  function buildOptionsObjectFromTable() {
    const obj = {}
    for (const row of optionsTableData.value) {
      if (!row.key.trim()) {
        continue
      }
      if (row.isComplex) {
        try {
          obj[row.key] = JSON.parse(row.value)
        } catch {
          obj[row.key] = row.value
        }
      } else {
        obj[row.key] = row.value
      }
    }
    return obj
  }

  /** Sync table data to JSON text. */
  function syncTableToJson() {
    const obj = buildOptionsObjectFromTable()
    optionsJsonText.value = Object.keys(obj).length > 0 ? JSON.stringify(obj, null, 2) : ''
    optionsJsonError.value = ''
  }

  /**
   * Sync JSON text to table data
   */
  function syncJsonToTable() {
    optionsJsonError.value = ''

    if (!optionsJsonText.value.trim()) {
      optionsTableData.value = []
      return true
    }

    try {
      const parsed = JSON.parse(optionsJsonText.value)
      if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
        optionsJsonError.value = _('options_json_must_be_object')
        return false
      }

      optionsTableData.value = Object.entries(parsed).map(([key, value]) => ({
        key,
        value: isComplexValue(value) ? JSON.stringify(value, null, 2) : value,
        isComplex: isComplexValue(value),
      }))
      return true
    } catch (e) {
      optionsJsonError.value = _('options_json_invalid') + ': ' + e.message
      return false
    }
  }

  /**
   * Switch options edit mode
   */
  function switchOptionsMode(mode) {
    if (mode === optionsEditMode.value) return

    if (mode === 'json') {
      syncTableToJson()
    } else {
      if (!syncJsonToTable()) {
        return
      }
    }

    optionsEditMode.value = mode
  }

  /**
   * Get options object for saving
   */
  function getOptionsForSave() {
    if (optionsEditMode.value === 'json') {
      if (!optionsJsonText.value.trim()) return null
      try {
        return JSON.parse(optionsJsonText.value)
      } catch {
        return null
      }
    }
    const obj = buildOptionsObjectFromTable()
    return Object.keys(obj).length > 0 ? obj : null
  }

  return {
    optionsEditMode,
    optionsTableData,
    optionsJsonText,
    optionsJsonError,
    productOptionFields,
    loadingOptionFields,
    loadProductOptionFields,
    initOptionsFromProduct,
    onOptionTypeChange,
    onFieldKeyChange,
    addOptionRow,
    removeOptionRow,
    syncTableToJson,
    switchOptionsMode,
    getOptionsForSave,
  }
}
