<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import draggable from 'vuedraggable'

import { GridColumnEditorType, normalizeGridColumnEditorType } from '../constants/gridColumnEditorTypes.js'
import request from '../request.js'
import { isAllowlistedComboEndpoint } from '../utils/gridEditorOptions.js'
import ActionsEditor from './ActionsEditor.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const loading = ref(false)
const saving = ref(false)
const fields = ref([])
const selectedGrid = ref('customers')

const showAddDialog = ref(false)
const newField = ref({
  field_name: '',
  label: '',
  type: 'model',
  visible: true,
  sortable: false,
  filterable: false,
  frozen: false,
  width: '',
  config: {
    template: '',
    relation: {
      table: '',
      foreignKey: '',
      displayField: '',
      aggregation: null,
    },
    computed: {
      className: '',
    },
    actions: [],
    displayConfig: '',
    badge: {
      source_field: '',
      color_field: '',
    },
    editable: false,
    editor_type: GridColumnEditorType.TEXT,
    editor_options: [],
    editor_reference: '',
    editor_combo_endpoint: '',
  },
})

const showEditDialog = ref(false)
const editingField = ref(null)
const editingFieldIndex = ref(null)

/** Raw JSON string for editor_options in Add field dialog (textarea) */
const newFieldEditorOptionsJson = ref('[]')
/** Raw JSON string for editor_options in Edit field dialog (textarea) */
const editingFieldEditorOptionsJson = ref('[]')

/**
 * Available grids
 */
const gridOptions = computed(() => [
  { label: _('grid_customers'), value: 'customers' },
  { label: _('grid_orders'), value: 'orders' },
  { label: _('grid_order_products'), value: 'order_products' },
  { label: _('grid_vendors'), value: 'vendors' },
  { label: _('grid_category_products'), value: 'category-products' },
])

/**
 * Field types
 */
const fieldTypeOptions = computed(() => [
  { label: _('field_type_model'), value: 'model' },
  { label: _('field_type_template'), value: 'template' },
  { label: _('field_type_relation'), value: 'relation' },
  { label: _('field_type_computed'), value: 'computed' },
  { label: _('field_type_image'), value: 'image' },
  { label: _('field_type_boolean'), value: 'boolean' },
  { label: _('field_type_badge'), value: 'badge' },
  { label: _('field_type_datetime'), value: 'datetime' },
  { label: _('field_type_price'), value: 'price' },
  { label: _('field_type_weight'), value: 'weight' },
  { label: _('field_type_actions'), value: 'actions' },
])

/**
 * Types that require display config (JSON editor)
 */
const displayConfigTypes = ['datetime', 'price', 'weight']

/**
 * Inline edit: only for category-products grid
 */
const isCategoryProductsGrid = computed(() => selectedGrid.value === 'category-products')

/**
 * Parse editor_options from array or JSON string. Returns array or null if invalid.
 */
function parseEditorOptions(value) {
  if (Array.isArray(value)) return value
  if (typeof value !== 'string' || !value.trim()) return []
  try {
    const parsed = JSON.parse(value)
    return Array.isArray(parsed) ? parsed : null
  } catch {
    return null
  }
}

/**
 * Serialize editor_options for textarea display
 */
function editorOptionsToJson(editorOptions) {
  const arr = Array.isArray(editorOptions) ? editorOptions : []
  try {
    return JSON.stringify(arr, null, 2)
  } catch {
    return '[]'
  }
}

/**
 * Editor type options for editable columns (text, number, select, combo)
 */
const editorTypeOptions = computed(() => [
  { label: _('editor_type_text'), value: GridColumnEditorType.TEXT },
  { label: _('editor_type_number'), value: GridColumnEditorType.NUMBER },
  { label: _('editor_type_select'), value: GridColumnEditorType.SELECT },
  { label: _('editor_type_combo'), value: GridColumnEditorType.COMBO },
])

/** From GET grid-config when grid is category-products */
const editorReferenceList = ref([])

const editorReferenceSelectOptions = computed(() =>
  editorReferenceList.value.map(r => ({
    label: `${r.key} (${r.path})`,
    value: r.key,
  }))
)

const editorReferenceOptionsWithEmpty = computed(() => [
  { label: _('editor_reference_none'), value: '' },
  ...editorReferenceSelectOptions.value,
])

/**
 * Get config hint for display type
 */
function getConfigHint(type) {
  const hints = {
    datetime: '{ "format": "dd.MM.yyyy HH:mm" }',
    price:
      '{ "decimals": 2, "currency": "₽", "currency_position": "after", "thousands_separator": " " }',
    weight: '{ "decimals": 2, "unit": "кг", "unit_position": "after" }',
  }
  return hints[type] || ''
}

/**
 * Get available fields for badge source_field/color_field selection
 * Excludes current field and badge/computed types to prevent recursion
 */
function getAvailableFieldsForBadge(currentFieldName = '') {
  // Filter: exclude current field and badge/computed types
  const excludedTypes = ['badge', 'computed', 'actions']
  return fields.value
    .filter(f => f.name !== currentFieldName && !excludedTypes.includes(f.type))
    .map(f => ({
      label: f.label || f.name,
      value: f.name,
    }))
}

/**
 * Aggregation types for relation fields
 */
const aggregationOptions = computed(() => [
  { label: _('relation_aggregation_none'), value: null },
  { label: _('relation_aggregation_count'), value: 'COUNT' },
  { label: _('relation_aggregation_sum'), value: 'SUM' },
  { label: _('relation_aggregation_avg'), value: 'AVG' },
  { label: _('relation_aggregation_min'), value: 'MIN' },
  { label: _('relation_aggregation_max'), value: 'MAX' },
])

/**
 * Load grid fields configuration
 */
async function loadFields() {
  loading.value = true

  try {
    // include_hidden=1 to get all fields for configuration (including hidden relation fields)
    const response = await request.get(`/api/mgr/grid-config/${selectedGrid.value}`, {
      include_hidden: '1',
    })

    if (response && response.columns) {
      editorReferenceList.value = Array.isArray(response.editor_references)
        ? response.editor_references
        : []
      fields.value = response.columns.map((col, index) => ({
        name: col.name,
        label: col.label,
        visible: col.visible !== false,
        sortable: col.sortable !== false,
        filterable: col.filterable === true,
        frozen: col.frozen === true,
        width: col.width || '',
        minWidth: col.minWidth || '',
        isSystem: col.isSystem === true,
        sort_order: index,
        // Type-specific config
        type: col.type || 'model',
        template: col.template || '',
        relation: col.relation || null,
        computed: col.computed || null,
        actions: col.actions || null,
        // Display config
        format: col.format || '',
        source_field: col.source_field || '',
        color_field: col.color_field || '',
        decimals: col.decimals,
        currency: col.currency || '',
        currency_position: col.currency_position || '',
        thousands_separator: col.thousands_separator || '',
        decimal_separator: col.decimal_separator || '',
        unit: col.unit || '',
        unit_position: col.unit_position || '',
        editable: col.editable === true,
        editor_type: normalizeGridColumnEditorType(col.editor_type),
        editor_options: Array.isArray(col.editor_options) ? col.editor_options : [],
        editor_reference: col.editor_reference || '',
        editor_combo_endpoint: col.editor_combo_endpoint || '',
      }))
    } else {
      console.error('[GridFieldsConfig] Invalid response:', response)
      fields.value = []
      editorReferenceList.value = []
    }
  } catch (error) {
    console.error('[GridFieldsConfig] Error loading fields:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_loading_data'),
      life: 5000,
    })
  } finally {
    loading.value = false
  }
}

/**
 * Save configuration
 */
async function saveConfig() {
  saving.value = true

  try {
    const fieldsData = fields.value.map((field, index) => {
      const data = {
        name: field.name,
        label: field.label || null,
        visible: field.visible,
        sortable: field.sortable,
        filterable: field.filterable,
        frozen: field.frozen,
        sort_order: index,
        width: field.width || null,
        minWidth: field.minWidth || null,
      }

      // Type and type-specific config
      if (field.type) data.type = field.type
      if (field.template) data.template = field.template
      if (field.relation) data.relation = field.relation
      if (field.computed) data.computed = field.computed
      if (field.actions) data.actions = field.actions

      // Display config
      if (field.format) data.format = field.format
      if (field.source_field) data.source_field = field.source_field
      if (field.color_field) data.color_field = field.color_field
      if (field.decimals !== undefined) data.decimals = field.decimals
      if (field.currency) data.currency = field.currency
      if (field.currency_position) data.currency_position = field.currency_position
      if (field.thousands_separator) data.thousands_separator = field.thousands_separator
      if (field.decimal_separator) data.decimal_separator = field.decimal_separator
      if (field.unit) data.unit = field.unit
      if (field.unit_position) data.unit_position = field.unit_position

      if (selectedGrid.value === 'category-products') {
        data.editable = field.editable === true
        if (field.editor_type) data.editor_type = field.editor_type
        if (Array.isArray(field.editor_options)) data.editor_options = field.editor_options
        data.editor_reference = field.editor_reference != null ? String(field.editor_reference) : ''
        data.editor_combo_endpoint =
          field.editor_combo_endpoint != null ? String(field.editor_combo_endpoint) : ''
      }

      return data
    })

    await request.put(`/api/mgr/grid-config/${selectedGrid.value}`, {
      fields: fieldsData,
    })

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('grid_config_saved'),
      life: 3000,
    })
  } catch (error) {
    console.error('[GridFieldsConfig] Error saving config:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000,
    })
  } finally {
    saving.value = false
  }
}

/**
 * Handle row order change (VueDraggable)
 */
function onDragEnd() {
  toast.add({
    severity: 'info',
    summary: _('success'),
    detail: _('order_changed_save_reminder'),
    life: 3000,
  })
}

/**
 * Delete field
 */
function deleteField(field, index) {
  if (field.isSystem) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('cannot_delete_system_field'),
      life: 3000,
    })
    return
  }

  const confirmMessage = _('delete_field_confirm_message').replace(
    '{name}',
    field.label || field.name
  )
  const confirmHeader = _('delete_field_confirm_title')
  const deleteLabel = _('delete')
  const cancelLabel = _('cancel')
  const successSummary = _('success')
  const successDetail = _('field_deleted')
  const errorSummary = _('error')
  const errorDetail = _('error_deleting_field')

  confirm.require({
    group: 'grid-fields-config',
    message: confirmMessage,
    header: confirmHeader,
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: deleteLabel,
    rejectLabel: cancelLabel,
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/grid-config/${selectedGrid.value}/${field.name}`)

        fields.value.splice(index, 1)

        toast.add({
          severity: 'success',
          summary: successSummary,
          detail: successDetail,
          life: 3000,
        })
      } catch (error) {
        console.error('[GridFieldsConfig] Error deleting field:', error)
        toast.add({
          severity: 'error',
          summary: errorSummary,
          detail: error.message || errorDetail,
          life: 5000,
        })
      }
    },
  })
}

function onGridChange() {
  loadFields()
}

function openAddDialog() {
  newField.value = {
    field_name: '',
    label: '',
    type: 'model',
    visible: true,
    sortable: false,
    filterable: false,
    frozen: false,
    width: '',
    config: {
      template: '',
      relation: {
        table: '',
        foreignKey: '',
        displayField: '',
        aggregation: null,
      },
      computed: {
        className: '',
      },
      actions: [
        { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
        {
          name: 'delete',
          handler: 'delete',
          icon: 'pi-trash',
          label: 'delete',
          severity: 'danger',
          confirm: true,
        },
      ],
      displayConfig: '',
      badge: {
        source_field: '',
        color_field: '',
      },
      editable: false,
      editor_type: GridColumnEditorType.TEXT,
      editor_options: [],
      editor_reference: '',
      editor_combo_endpoint: '',
    },
  }
  newFieldEditorOptionsJson.value = editorOptionsToJson(newField.value.config.editor_options)
  showAddDialog.value = true
}

function closeAddDialog() {
  showAddDialog.value = false
}

/**
 * Add new field
 */
async function addField() {
  try {
    const data = {
      field_name: newField.value.field_name,
      label: newField.value.label,
      type: newField.value.type,
      visible: newField.value.visible,
      sortable: newField.value.sortable,
      filterable: newField.value.filterable,
      frozen: newField.value.frozen,
      width: newField.value.width || null,
      config: {},
    }

    switch (newField.value.type) {
      case 'template':
        data.config = {
          template: newField.value.config.template,
        }
        break
      case 'relation':
        data.config = {
          relation: {
            table: newField.value.config.relation.table,
            foreignKey: newField.value.config.relation.foreignKey,
            displayField: newField.value.config.relation.displayField,
            aggregation: newField.value.config.relation.aggregation,
          },
        }
        break
      case 'computed':
        data.config = {
          computed: {
            className: newField.value.config.computed.className,
          },
        }
        break
      case 'actions':
        data.config = {
          actions: newField.value.config.actions || [],
        }
        data.sortable = false
        data.filterable = false
        break
      case 'badge':
        // Badge uses source_field and color_field dropdowns
        data.config = {}
        if (newField.value.config.badge.source_field) {
          data.config.source_field = newField.value.config.badge.source_field
        }
        if (newField.value.config.badge.color_field) {
          data.config.color_field = newField.value.config.badge.color_field
        }
        break
      case 'datetime':
      case 'price':
      case 'weight':
        // Parse displayConfig JSON and merge into config
        if (newField.value.config.displayConfig) {
          try {
            const displayConfig = JSON.parse(newField.value.config.displayConfig)
            data.config = { ...displayConfig }
          } catch {
            toast.add({
              severity: 'error',
              summary: _('error'),
              detail: _('invalid_json_config'),
              life: 5000,
            })
            return
          }
        }
        break
    }

    if (selectedGrid.value === 'category-products') {
      data.config.editable = newField.value.config.editable === true
      data.config.editor_type = normalizeGridColumnEditorType(newField.value.config.editor_type)
      if (newField.value.config.editor_type === GridColumnEditorType.SELECT) {
        const opts = parseEditorOptions(newFieldEditorOptionsJson.value)
        if (opts === null) {
          toast.add({
            severity: 'error',
            summary: _('error'),
            detail: _('invalid_json_config'),
            life: 5000,
          })
          return
        }
        data.config.editor_options = opts
        data.config.editor_reference = ''
        data.config.editor_combo_endpoint = ''
      } else if (newField.value.config.editor_type === GridColumnEditorType.COMBO) {
        const ref = String(newField.value.config.editor_reference || '').trim()
        const endpoint = String(newField.value.config.editor_combo_endpoint || '').trim()
        if (endpoint && !isAllowlistedComboEndpoint(endpoint)) {
          toast.add({
            severity: 'error',
            summary: _('error'),
            detail: _('editor_combo_endpoint_not_allowlisted'),
            life: 5000,
          })
          return
        }
        const refOk = ref !== '' && editorReferenceList.value.some(r => r.key === ref)
        if (!refOk && !endpoint) {
          toast.add({
            severity: 'error',
            summary: _('error'),
            detail: _('editor_combo_ref_or_endpoint_required'),
            life: 5000,
          })
          return
        }
        data.config.editor_reference = refOk ? ref : ''
        data.config.editor_combo_endpoint = endpoint
      } else {
        data.config.editor_options = []
        data.config.editor_reference = ''
        data.config.editor_combo_endpoint = ''
      }
    }

    const result = await request.post(`/api/mgr/grid-config/${selectedGrid.value}/field`, data)

    if (result.field) {
      // Parse config from response (may be JSON string or already parsed)
      let config = result.field.config
      if (typeof config === 'string') {
        try {
          config = JSON.parse(config)
        } catch {
          config = {}
        }
      }
      config = config || {}

      fields.value.push({
        name: result.field.field_name,
        label: result.field.label,
        visible: result.field.visible,
        sortable: result.field.sortable,
        filterable: result.field.filterable,
        frozen: result.field.frozen,
        width: result.field.width || '',
        minWidth: result.field.min_width || '',
        isSystem: result.field.is_system,
        sort_order: result.field.sort_order,
        // Type-specific config from parsed JSON
        type: config.type || 'model',
        template: config.template || '',
        relation: config.relation || null,
        computed: config.computed || null,
        actions: config.actions || null,
        // Display config
        format: config.format || '',
        source_field: config.source_field || '',
        color_field: config.color_field || '',
        decimals: config.decimals,
        currency: config.currency || '',
        currency_position: config.currency_position || '',
        thousands_separator: config.thousands_separator || '',
        decimal_separator: config.decimal_separator || '',
        unit: config.unit || '',
        unit_position: config.unit_position || '',
        editable: config.editable === true,
        editor_type: normalizeGridColumnEditorType(config.editor_type),
        editor_options: Array.isArray(config.editor_options) ? config.editor_options : [],
        editor_reference: config.editor_reference || '',
        editor_combo_endpoint: config.editor_combo_endpoint || '',
      })
    }

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('field_added'),
      life: 3000,
    })

    closeAddDialog()
  } catch (error) {
    console.error('[GridFieldsConfig] Error adding field:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_adding_field'),
      life: 5000,
    })
  }
}

/**
 * Open edit field dialog
 */
function openEditDialog(field, index) {
  editingFieldIndex.value = index

  const fieldType = field.type || 'model'

  // Build displayConfig JSON from field properties for display types (datetime, price, weight)
  let displayConfig = ''
  if (displayConfigTypes.includes(fieldType)) {
    const configObj = {}
    // Extract display-related properties from field
    if (fieldType === 'datetime' && field.format) {
      configObj.format = field.format
    }
    if (fieldType === 'price') {
      if (field.decimals !== undefined) configObj.decimals = field.decimals
      if (field.currency) configObj.currency = field.currency
      if (field.currency_position) configObj.currency_position = field.currency_position
      if (field.thousands_separator) configObj.thousands_separator = field.thousands_separator
      if (field.decimal_separator) configObj.decimal_separator = field.decimal_separator
    }
    if (fieldType === 'weight') {
      if (field.decimals !== undefined) configObj.decimals = field.decimals
      if (field.unit) configObj.unit = field.unit
      if (field.unit_position) configObj.unit_position = field.unit_position
    }
    if (Object.keys(configObj).length > 0) {
      displayConfig = JSON.stringify(configObj, null, 2)
    }
  }

  // Badge config (separate from displayConfig)
  const badgeConfig = {
    source_field: field.source_field || '',
    color_field: field.color_field || '',
  }

  // Load relation config from field data
  const relationConfig = field.relation || {
    table: '',
    foreignKey: '',
    displayField: '',
    aggregation: null,
  }

  // Load computed config from field data
  const computedConfig = field.computed || {
    className: '',
  }

  editingField.value = {
    field_name: field.name,
    label: field.label || '',
    type: fieldType,
    visible: field.visible !== false,
    sortable: field.sortable !== false,
    filterable: field.filterable === true,
    frozen: field.frozen === true,
    width: field.width || '',
    config: {
      template: field.template || '',
      relation: relationConfig,
      computed: computedConfig,
      actions: field.actions || [
        { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
        {
          name: 'delete',
          handler: 'delete',
          icon: 'pi-trash',
          label: 'delete',
          severity: 'danger',
          confirm: true,
        },
      ],
      displayConfig: displayConfig,
      badge: badgeConfig,
      editable: field.editable === true,
      editor_type: normalizeGridColumnEditorType(field.editor_type),
      editor_options: Array.isArray(field.editor_options) ? field.editor_options : [],
      editor_reference: field.editor_reference || '',
      editor_combo_endpoint: field.editor_combo_endpoint || '',
    },
  }

  editingFieldEditorOptionsJson.value = editorOptionsToJson(editingField.value.config.editor_options)
  showEditDialog.value = true
}

function closeEditDialog() {
  showEditDialog.value = false
  editingField.value = null
  editingFieldIndex.value = null
}

async function saveEdit() {
  try {
    const data = {
      field_name: editingField.value.field_name,
      label: editingField.value.label,
      type: editingField.value.type,
      visible: editingField.value.visible,
      sortable: editingField.value.sortable,
      filterable: editingField.value.filterable,
      frozen: editingField.value.frozen,
      width: editingField.value.width || null,
      config: {},
    }

    switch (editingField.value.type) {
      case 'template':
        data.config = {
          template: editingField.value.config.template,
        }
        break
      case 'relation':
        data.config = {
          relation: {
            table: editingField.value.config.relation.table,
            foreignKey: editingField.value.config.relation.foreignKey,
            displayField: editingField.value.config.relation.displayField,
            aggregation: editingField.value.config.relation.aggregation,
          },
        }
        break
      case 'computed':
        data.config = {
          computed: {
            className: editingField.value.config.computed.className,
          },
        }
        break
      case 'actions':
        data.config = {
          actions: editingField.value.config.actions || [],
        }
        data.sortable = false
        data.filterable = false
        break
      case 'badge':
        // Badge uses source_field and color_field dropdowns
        data.config = {}
        if (editingField.value.config.badge.source_field) {
          data.config.source_field = editingField.value.config.badge.source_field
        }
        if (editingField.value.config.badge.color_field) {
          data.config.color_field = editingField.value.config.badge.color_field
        }
        break
      case 'datetime':
      case 'price':
      case 'weight':
        // Parse displayConfig JSON and merge into config
        if (editingField.value.config.displayConfig) {
          try {
            const displayConfig = JSON.parse(editingField.value.config.displayConfig)
            data.config = { ...displayConfig }
          } catch {
            toast.add({
              severity: 'error',
              summary: _('error'),
              detail: _('invalid_json_config'),
              life: 5000,
            })
            return
          }
        }
        break
    }

    if (selectedGrid.value === 'category-products') {
      data.config.editable = editingField.value.config.editable === true
      data.config.editor_type = normalizeGridColumnEditorType(editingField.value.config.editor_type)
      if (editingField.value.config.editor_type === GridColumnEditorType.SELECT) {
        const opts = parseEditorOptions(editingFieldEditorOptionsJson.value)
        if (opts === null) {
          toast.add({
            severity: 'error',
            summary: _('error'),
            detail: _('invalid_json_config'),
            life: 5000,
          })
          return
        }
        data.config.editor_options = opts
        data.config.editor_reference = ''
        data.config.editor_combo_endpoint = ''
      } else if (editingField.value.config.editor_type === GridColumnEditorType.COMBO) {
        const ref = String(editingField.value.config.editor_reference || '').trim()
        const endpoint = String(editingField.value.config.editor_combo_endpoint || '').trim()
        if (endpoint && !isAllowlistedComboEndpoint(endpoint)) {
          toast.add({
            severity: 'error',
            summary: _('error'),
            detail: _('editor_combo_endpoint_not_allowlisted'),
            life: 5000,
          })
          return
        }
        const refOk = ref !== '' && editorReferenceList.value.some(r => r.key === ref)
        if (!refOk && !endpoint) {
          toast.add({
            severity: 'error',
            summary: _('error'),
            detail: _('editor_combo_ref_or_endpoint_required'),
            life: 5000,
          })
          return
        }
        data.config.editor_reference = refOk ? ref : ''
        data.config.editor_combo_endpoint = endpoint
      } else {
        data.config.editor_options = []
        data.config.editor_reference = ''
        data.config.editor_combo_endpoint = ''
      }
    }

    const result = await request.put(
      `/api/mgr/grid-config/${selectedGrid.value}/field/${editingField.value.field_name}`,
      data
    )

    if (editingFieldIndex.value !== null && result.field) {
      // Parse config from response (may be JSON string or already parsed)
      let config = result.field.config
      if (typeof config === 'string') {
        try {
          config = JSON.parse(config)
        } catch {
          config = {}
        }
      }
      config = config || {}

      fields.value[editingFieldIndex.value] = {
        name: result.field.field_name,
        label: result.field.label,
        visible: result.field.visible,
        sortable: result.field.sortable,
        filterable: result.field.filterable,
        frozen: result.field.frozen,
        width: result.field.width || '',
        minWidth: result.field.min_width || '',
        isSystem: result.field.is_system,
        sort_order: result.field.sort_order,
        // Type-specific config from parsed JSON
        type: config.type || 'model',
        template: config.template || '',
        relation: config.relation || null,
        computed: config.computed || null,
        actions: config.actions || null,
        // Display config
        format: config.format || '',
        source_field: config.source_field || '',
        color_field: config.color_field || '',
        decimals: config.decimals,
        currency: config.currency || '',
        currency_position: config.currency_position || '',
        thousands_separator: config.thousands_separator || '',
        decimal_separator: config.decimal_separator || '',
        unit: config.unit || '',
        unit_position: config.unit_position || '',
        editable: config.editable === true,
        editor_type: normalizeGridColumnEditorType(config.editor_type),
        editor_options: Array.isArray(config.editor_options) ? config.editor_options : [],
        editor_reference: config.editor_reference || '',
        editor_combo_endpoint: config.editor_combo_endpoint || '',
      }
    }

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('field_updated'),
      life: 3000,
    })

    closeEditDialog()
  } catch (error) {
    console.error('[GridFieldsConfig] Error updating field:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_updating_field'),
      life: 5000,
    })
  }
}

onMounted(() => {
  loadFields()
})
</script>

<template>
  <div class="grid-fields-config">
    <Toast />
    <ConfirmDialog group="grid-fields-config" append-to="self" />

    <p class="tab-description">{{ _('ms3_utilities_grid_fields_description') }}</p>

    <div class="flex justify-content-between align-items-center mb-3">
      <div class="flex align-items-center gap-2">
        <label for="grid-select">{{ _('select_grid') }}</label>
        <Select
          id="grid-select"
          v-model="selectedGrid"
          :options="gridOptions"
          option-label="label"
          option-value="value"
          style="min-width: 12.5rem"
          @change="onGridChange"
        />
      </div>
      <Button :label="_('add_field')" icon="pi pi-plus" @click="openAddDialog" />
    </div>

    <p v-if="isCategoryProductsGrid" class="inline-edit-hint">
      {{ _('inline_edit_hint') }}
    </p>

    <Card>
      <template #content>
        <!-- Fields table with VueDraggable -->
        <div v-if="!loading" class="p-datatable p-component p-datatable-striped">
          <div class="p-datatable-wrapper">
            <table class="p-datatable-table">
              <thead class="p-datatable-thead">
                <tr>
                  <th style="width: 4rem"></th>
                  <th style="width: 12.5rem">{{ _('field_name') }}</th>
                  <th style="min-width: 12.5rem">{{ _('field_label') }}</th>
                  <th style="width: 6.25rem">{{ _('visible') }}</th>
                  <th style="width: 6.25rem">{{ _('sortable') }}</th>
                  <th style="width: 6.25rem">{{ _('filterable') }}</th>
                  <th style="width: 6.25rem">{{ _('frozen') }}</th>
                  <th v-if="isCategoryProductsGrid" style="width: 6.25rem">
                    {{ _('field_editable') }}
                  </th>
                  <th style="width: 7.5rem">{{ _('width') }}</th>
                  <th style="width: 6.25rem">{{ _('actions') }}</th>
                </tr>
              </thead>
              <draggable
                v-model="fields"
                tag="tbody"
                class="p-datatable-tbody"
                handle=".drag-handle"
                item-key="name"
                :animation="200"
                ghost-class="ghost-row"
                @end="onDragEnd"
              >
                <template #item="{ element: field, index }">
                  <tr>
                    <td class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>{{ field.name }}</td>
                    <td>
                      <InputText v-model="field.label" class="w-full" />
                    </td>
                    <td>
                      <Checkbox v-model="field.visible" :binary="true" :disabled="field.isSystem" />
                    </td>
                    <td>
                      <Checkbox v-model="field.sortable" :binary="true" />
                    </td>
                    <td>
                      <Checkbox v-model="field.filterable" :binary="true" />
                    </td>
                    <td>
                      <Checkbox v-model="field.frozen" :binary="true" />
                    </td>
                    <td v-if="isCategoryProductsGrid">
                      <Checkbox v-model="field.editable" :binary="true" />
                    </td>
                    <td>
                      <InputText v-model="field.width" placeholder="9.375rem" class="w-full" />
                    </td>
                    <td>
                      <Button
                        icon="pi pi-pencil"
                        size="small"
                        text
                        :title="_('edit')"
                        class="mr-2"
                        @click="openEditDialog(field, index)"
                      />
                      <Button
                        icon="pi pi-trash"
                        size="small"
                        severity="danger"
                        text
                        :title="_('delete')"
                        :disabled="field.isSystem"
                        @click="deleteField(field, index)"
                      />
                    </td>
                  </tr>
                </template>
              </draggable>
            </table>
          </div>
        </div>

        <!-- Loading indicator -->
        <div v-if="loading" class="loading-indicator">
          <i class="pi pi-spinner pi-spin" style="font-size: 2rem"></i>
        </div>

        <!-- Save button -->
        <div class="flex justify-content-end">
          <Button :label="_('save')" icon="pi pi-check" :loading="saving" @click="saveConfig" />
        </div>
      </template>
    </Card>

    <!-- Add field dialog -->
    <Dialog
      v-model:visible="showAddDialog"
      :header="_('add_field_dialog_title')"
      :modal="true"
      :closable="true"
      :style="{ width: '37.5rem' }"
      append-to="self"
      @hide="closeAddDialog"
    >
      <div class="field mb-3">
        <label for="new-field-name" class="required">{{ _('field_name') }}</label>
        <InputText
          id="new-field-name"
          v-model="newField.field_name"
          class="w-full"
          :placeholder="_('field_name_placeholder')"
        />
      </div>

      <div class="field mb-3">
        <label for="new-field-label">{{ _('field_label') }}</label>
        <InputText
          id="new-field-label"
          v-model="newField.label"
          class="w-full"
          :placeholder="_('field_label_placeholder')"
        />
      </div>

      <div class="field mb-3">
        <label for="new-field-type" class="required">{{ _('field_type') }}</label>
        <Select
          id="new-field-type"
          v-model="newField.type"
          :options="fieldTypeOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <!-- Dynamic fields based on type -->
      <div v-if="newField.type === 'template'" class="field mb-3">
        <label for="new-field-template" class="required">{{ _('field_template') }}</label>
        <Textarea
          id="new-field-template"
          v-model="newField.config.template"
          rows="3"
          class="w-full"
          :placeholder="_('field_template_placeholder')"
        />
        <small class="text-muted">{{ _('field_template_hint') }}</small>
      </div>

      <div v-if="newField.type === 'relation'" class="mb-3">
        <div class="field mb-2">
          <label for="new-field-relation-table" class="required">{{ _('relation_table') }}</label>
          <InputText
            id="new-field-relation-table"
            v-model="newField.config.relation.table"
            class="w-full"
            :placeholder="_('relation_table_placeholder')"
          />
        </div>
        <div class="field mb-2">
          <label for="new-field-relation-fk" class="required">{{
            _('relation_foreign_key')
          }}</label>
          <InputText
            id="new-field-relation-fk"
            v-model="newField.config.relation.foreignKey"
            class="w-full"
            :placeholder="_('relation_foreign_key_placeholder')"
          />
        </div>
        <div class="field mb-2">
          <label for="new-field-relation-display" class="required">{{
            _('relation_display_field')
          }}</label>
          <InputText
            id="new-field-relation-display"
            v-model="newField.config.relation.displayField"
            class="w-full"
            :placeholder="_('relation_display_field_placeholder')"
          />
        </div>
        <div class="field mb-2">
          <label for="new-field-relation-aggregation">{{ _('relation_aggregation') }}</label>
          <Select
            id="new-field-relation-aggregation"
            v-model="newField.config.relation.aggregation"
            :options="aggregationOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <small class="text-muted">{{ _('relation_hint') }}</small>
      </div>

      <div v-if="newField.type === 'computed'" class="field mb-3">
        <label for="new-field-computed-class" class="required">{{
          _('computed_class_name')
        }}</label>
        <InputText
          id="new-field-computed-class"
          v-model="newField.config.computed.className"
          class="w-full"
          :placeholder="_('computed_class_name_placeholder')"
        />
        <small class="text-muted">{{ _('computed_class_hint') }}</small>
      </div>

      <!-- Actions configuration for actions type -->
      <div v-if="newField.type === 'actions'" class="field mb-3">
        <label class="mb-2 block font-semibold">{{ _('actions_configuration') }}</label>
        <ActionsEditor v-model="newField.config.actions" :grid-id="selectedGrid" />
        <small class="text-muted">{{ _('actions_configuration_hint') }}</small>
      </div>

      <!-- Badge configuration -->
      <div v-if="newField.type === 'badge'" class="mb-3">
        <div class="field mb-2">
          <label for="new-field-badge-source">{{ _('field_source_field') }}</label>
          <Select
            id="new-field-badge-source"
            v-model="newField.config.badge.source_field"
            :options="getAvailableFieldsForBadge(newField.field_name)"
            option-label="label"
            option-value="value"
            :placeholder="_('field_source_field_placeholder')"
            :show-clear="true"
            class="w-full"
          />
          <small class="text-muted">{{ _('field_source_field_hint') }}</small>
        </div>
        <div class="field mb-2">
          <label for="new-field-badge-color">{{ _('field_color_field') }}</label>
          <Select
            id="new-field-badge-color"
            v-model="newField.config.badge.color_field"
            :options="getAvailableFieldsForBadge(newField.field_name)"
            option-label="label"
            option-value="value"
            :placeholder="_('field_color_field_placeholder')"
            :show-clear="true"
            class="w-full"
          />
          <small class="text-muted">{{ _('field_color_field_hint') }}</small>
        </div>
      </div>

      <!-- Display config for datetime, price, weight -->
      <div v-if="displayConfigTypes.includes(newField.type)" class="field mb-3">
        <label for="new-field-display-config">{{ _('field_display_config') }}</label>
        <Textarea
          id="new-field-display-config"
          v-model="newField.config.displayConfig"
          rows="3"
          class="w-full font-mono"
          :placeholder="getConfigHint(newField.type)"
        />
        <small class="text-muted"
          >{{ _('field_display_config_hint') }}: {{ getConfigHint(newField.type) }}</small
        >
      </div>

      <!-- Inline edit (category-products only) -->
      <div v-if="isCategoryProductsGrid" class="field mb-3">
        <div class="flex align-items-center mb-2">
          <Checkbox
            v-model="newField.config.editable"
            input-id="new-field-editable"
            :binary="true"
          />
          <label for="new-field-editable" class="ml-2 cursor-pointer">{{
            _('field_editable')
          }}</label>
        </div>
        <div v-if="newField.config.editable" class="ml-4">
          <label for="new-field-editor-type">{{ _('editor_type') }}</label>
          <Select
            id="new-field-editor-type"
            v-model="newField.config.editor_type"
            :options="editorTypeOptions"
            option-label="label"
            option-value="value"
            class="w-full mt-1"
          />
          <div v-if="newField.config.editor_type === GridColumnEditorType.SELECT" class="mt-2">
            <label for="new-field-editor-options">{{ _('editor_options') }}</label>
            <Textarea
              id="new-field-editor-options"
              v-model="newFieldEditorOptionsJson"
              rows="4"
              class="w-full font-mono mt-1"
              :placeholder="_('editor_options_hint')"
            />
          </div>
          <div v-if="newField.config.editor_type === GridColumnEditorType.COMBO" class="mt-2">
            <label for="new-field-editor-reference">{{ _('editor_reference') }}</label>
            <Select
              id="new-field-editor-reference"
              v-model="newField.config.editor_reference"
              :options="editorReferenceOptionsWithEmpty"
              option-label="label"
              option-value="value"
              class="w-full mt-1"
              show-clear
            />
            <small class="text-muted block mt-1">{{ _('editor_reference_hint') }}</small>
            <label for="new-field-editor-combo-endpoint" class="block mt-2">{{
              _('editor_combo_endpoint_override')
            }}</label>
            <InputText
              id="new-field-editor-combo-endpoint"
              v-model="newField.config.editor_combo_endpoint"
              class="w-full mt-1"
              :placeholder="_('editor_combo_endpoint_placeholder')"
            />
            <small class="text-muted block mt-1">{{ _('editor_combo_endpoint_override_hint') }}</small>
          </div>
        </div>
      </div>

      <!-- General settings -->
      <div class="field mb-3">
        <label for="new-field-width">{{ _('width') }}</label>
        <InputText
          id="new-field-width"
          v-model="newField.width"
          class="w-full"
          placeholder="9.375rem"
        />
      </div>

      <div class="flex flex-wrap gap-4 mb-3">
        <div class="flex align-items-center">
          <Checkbox v-model="newField.visible" input-id="new-field-visible" :binary="true" />
          <label for="new-field-visible" class="ml-2 cursor-pointer">{{ _('visible') }}</label>
        </div>

        <div class="flex align-items-center">
          <Checkbox
            v-model="newField.sortable"
            input-id="new-field-sortable"
            :binary="true"
            :disabled="newField.type === 'actions'"
          />
          <label
            for="new-field-sortable"
            class="ml-2 cursor-pointer"
            :class="{ 'opacity-50': newField.type === 'actions' }"
            >{{ _('sortable') }}</label
          >
        </div>

        <div class="flex align-items-center">
          <Checkbox
            v-model="newField.filterable"
            input-id="new-field-filterable"
            :binary="true"
            :disabled="newField.type === 'template' || newField.type === 'actions'"
          />
          <label
            for="new-field-filterable"
            class="ml-2 cursor-pointer"
            :class="{ 'opacity-50': newField.type === 'template' || newField.type === 'actions' }"
            >{{ _('filterable') }}</label
          >
        </div>

        <div class="flex align-items-center">
          <Checkbox v-model="newField.frozen" input-id="new-field-frozen" :binary="true" />
          <label for="new-field-frozen" class="ml-2 cursor-pointer">{{ _('frozen') }}</label>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          severity="secondary"
          text
          @click="closeAddDialog"
        />
        <Button
          :label="_('create')"
          icon="pi pi-check"
          :disabled="!newField.field_name"
          @click="addField"
        />
      </template>
    </Dialog>

    <!-- Edit field dialog -->
    <Dialog
      v-model:visible="showEditDialog"
      :header="_('edit_field_dialog_title')"
      :modal="true"
      :closable="true"
      :style="{ width: '37.5rem' }"
      append-to="self"
      @hide="closeEditDialog"
    >
      <div v-if="editingField">
        <div class="field mb-3">
          <label for="edit-field-name" class="required">{{ _('field_name') }}</label>
          <InputText
            id="edit-field-name"
            v-model="editingField.field_name"
            class="w-full"
            disabled
          />
          <small class="text-muted">{{ _('field_name_readonly_hint') }}</small>
        </div>

        <div class="field mb-3">
          <label for="edit-field-label">{{ _('field_label') }}</label>
          <InputText
            id="edit-field-label"
            v-model="editingField.label"
            class="w-full"
            :placeholder="_('field_label_placeholder')"
          />
        </div>

        <div class="field mb-3">
          <label for="edit-field-type" class="required">{{ _('field_type') }}</label>
          <Select
            id="edit-field-type"
            v-model="editingField.type"
            :options="fieldTypeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>

        <!-- Dynamic fields based on type -->
        <div v-if="editingField.type === 'template'" class="field mb-3">
          <label for="edit-field-template" class="required">{{ _('field_template') }}</label>
          <Textarea
            id="edit-field-template"
            v-model="editingField.config.template"
            rows="3"
            class="w-full"
            :placeholder="_('field_template_placeholder')"
          />
          <small class="text-muted">{{ _('field_template_hint') }}</small>
        </div>

        <div v-if="editingField.type === 'relation'" class="mb-3">
          <div class="field mb-2">
            <label for="edit-field-relation-table" class="required">{{
              _('relation_table')
            }}</label>
            <InputText
              id="edit-field-relation-table"
              v-model="editingField.config.relation.table"
              class="w-full"
              :placeholder="_('relation_table_placeholder')"
            />
          </div>
          <div class="field mb-2">
            <label for="edit-field-relation-fk" class="required">{{
              _('relation_foreign_key')
            }}</label>
            <InputText
              id="edit-field-relation-fk"
              v-model="editingField.config.relation.foreignKey"
              class="w-full"
              :placeholder="_('relation_foreign_key_placeholder')"
            />
          </div>
          <div class="field mb-2">
            <label for="edit-field-relation-display" class="required">{{
              _('relation_display_field')
            }}</label>
            <InputText
              id="edit-field-relation-display"
              v-model="editingField.config.relation.displayField"
              class="w-full"
              :placeholder="_('relation_display_field_placeholder')"
            />
          </div>
          <div class="field mb-2">
            <label for="edit-field-relation-aggregation">{{ _('relation_aggregation') }}</label>
            <Select
              id="edit-field-relation-aggregation"
              v-model="editingField.config.relation.aggregation"
              :options="aggregationOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>
          <small class="text-muted">{{ _('relation_hint') }}</small>
        </div>

        <div v-if="editingField.type === 'computed'" class="field mb-3">
          <label for="edit-field-computed-class" class="required">{{
            _('computed_class_name')
          }}</label>
          <InputText
            id="edit-field-computed-class"
            v-model="editingField.config.computed.className"
            class="w-full"
            :placeholder="_('computed_class_name_placeholder')"
          />
          <small class="text-muted">{{ _('computed_class_hint') }}</small>
        </div>

        <!-- Actions configuration for actions type -->
        <div v-if="editingField.type === 'actions'" class="field mb-3">
          <label class="mb-2 block font-semibold">{{ _('actions_configuration') }}</label>
          <ActionsEditor v-model="editingField.config.actions" :grid-id="selectedGrid" />
          <small class="text-muted">{{ _('actions_configuration_hint') }}</small>
        </div>

        <!-- Badge configuration -->
        <div v-if="editingField.type === 'badge'" class="mb-3">
          <div class="field mb-2">
            <label for="edit-field-badge-source">{{ _('field_source_field') }}</label>
            <Select
              id="edit-field-badge-source"
              v-model="editingField.config.badge.source_field"
              :options="getAvailableFieldsForBadge(editingField.field_name)"
              option-label="label"
              option-value="value"
              :placeholder="_('field_source_field_placeholder')"
              :show-clear="true"
              class="w-full"
            />
            <small class="text-muted">{{ _('field_source_field_hint') }}</small>
          </div>
          <div class="field mb-2">
            <label for="edit-field-badge-color">{{ _('field_color_field') }}</label>
            <Select
              id="edit-field-badge-color"
              v-model="editingField.config.badge.color_field"
              :options="getAvailableFieldsForBadge(editingField.field_name)"
              option-label="label"
              option-value="value"
              :placeholder="_('field_color_field_placeholder')"
              :show-clear="true"
              class="w-full"
            />
            <small class="text-muted">{{ _('field_color_field_hint') }}</small>
          </div>
        </div>

        <!-- Display config for datetime, price, weight -->
        <div v-if="displayConfigTypes.includes(editingField.type)" class="field mb-3">
          <label for="edit-field-display-config">{{ _('field_display_config') }}</label>
          <Textarea
            id="edit-field-display-config"
            v-model="editingField.config.displayConfig"
            rows="3"
            class="w-full font-mono"
            :placeholder="getConfigHint(editingField.type)"
          />
          <small class="text-muted"
            >{{ _('field_display_config_hint') }}: {{ getConfigHint(editingField.type) }}</small
          >
        </div>

        <!-- Inline edit (category-products only) -->
        <div v-if="isCategoryProductsGrid" class="field mb-3">
          <div class="flex align-items-center mb-2">
            <Checkbox
              v-model="editingField.config.editable"
              input-id="edit-field-editable"
              :binary="true"
            />
            <label for="edit-field-editable" class="ml-2 cursor-pointer">{{
              _('field_editable')
            }}</label>
          </div>
          <div v-if="editingField.config.editable" class="ml-4">
            <label for="edit-field-editor-type">{{ _('editor_type') }}</label>
            <Select
              id="edit-field-editor-type"
              v-model="editingField.config.editor_type"
              :options="editorTypeOptions"
              option-label="label"
              option-value="value"
              class="w-full mt-1"
            />
            <div v-if="editingField.config.editor_type === GridColumnEditorType.SELECT" class="mt-2">
              <label for="edit-field-editor-options">{{ _('editor_options') }}</label>
              <Textarea
                id="edit-field-editor-options"
                v-model="editingFieldEditorOptionsJson"
                rows="4"
                class="w-full font-mono mt-1"
                :placeholder="_('editor_options_hint')"
              />
            </div>
            <div v-if="editingField.config.editor_type === GridColumnEditorType.COMBO" class="mt-2">
              <label for="edit-field-editor-reference">{{ _('editor_reference') }}</label>
              <Select
                id="edit-field-editor-reference"
                v-model="editingField.config.editor_reference"
                :options="editorReferenceOptionsWithEmpty"
                option-label="label"
                option-value="value"
                class="w-full mt-1"
                show-clear
              />
              <small class="text-muted block mt-1">{{ _('editor_reference_hint') }}</small>
              <label for="edit-field-editor-combo-endpoint" class="block mt-2">{{
                _('editor_combo_endpoint_override')
              }}</label>
              <InputText
                id="edit-field-editor-combo-endpoint"
                v-model="editingField.config.editor_combo_endpoint"
                class="w-full mt-1"
                :placeholder="_('editor_combo_endpoint_placeholder')"
              />
              <small class="text-muted block mt-1">{{ _('editor_combo_endpoint_override_hint') }}</small>
            </div>
          </div>
        </div>

        <!-- General settings -->
        <div class="field mb-3">
          <label for="edit-field-width">{{ _('width') }}</label>
          <InputText
            id="edit-field-width"
            v-model="editingField.width"
            class="w-full"
            placeholder="9.375rem"
          />
        </div>

        <div class="flex flex-wrap gap-4 mb-3">
          <div class="flex align-items-center">
            <Checkbox v-model="editingField.visible" input-id="edit-field-visible" :binary="true" />
            <label for="edit-field-visible" class="ml-2 cursor-pointer">{{ _('visible') }}</label>
          </div>

          <div class="flex align-items-center">
            <Checkbox
              v-model="editingField.sortable"
              input-id="edit-field-sortable"
              :binary="true"
              :disabled="editingField.type === 'actions'"
            />
            <label
              for="edit-field-sortable"
              class="ml-2 cursor-pointer"
              :class="{ 'opacity-50': editingField.type === 'actions' }"
              >{{ _('sortable') }}</label
            >
          </div>

          <div class="flex align-items-center">
            <Checkbox
              v-model="editingField.filterable"
              input-id="edit-field-filterable"
              :binary="true"
              :disabled="editingField.type === 'template' || editingField.type === 'actions'"
            />
            <label
              for="edit-field-filterable"
              class="ml-2 cursor-pointer"
              :class="{
                'opacity-50': editingField.type === 'template' || editingField.type === 'actions',
              }"
              >{{ _('filterable') }}</label
            >
          </div>

          <div class="flex align-items-center">
            <Checkbox v-model="editingField.frozen" input-id="edit-field-frozen" :binary="true" />
            <label for="edit-field-frozen" class="ml-2 cursor-pointer">{{ _('frozen') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          severity="secondary"
          text
          @click="closeEditDialog"
        />
        <Button
          :label="_('save')"
          icon="pi pi-check"
          :disabled="!editingField || !editingField.field_name"
          @click="saveEdit"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.grid-fields-config {
  padding: 1.25rem;
}

.drag-handle-cell {
  text-align: center;
  vertical-align: middle;
  padding: 0.5rem;
}

.drag-handle {
  cursor: grab;
  color: var(--ms3-text-muted);
  font-size: 1.2rem;
  padding: 0.5rem;
  user-select: none;
}

.drag-handle:hover {
  color: var(--ms3-text-hint);
}

.drag-handle:active {
  cursor: grabbing;
}

:deep(.ghost-row) {
  opacity: 0.5;
  background: var(--ms3-bg-muted);
}

:deep(.sortable-drag) {
  opacity: 0.8;
  background: var(--ms3-bg-neutral);
  cursor: grabbing !important;
}

.loading-indicator {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 3rem;
  color: var(--ms3-text-muted);
}

:deep(.p-datatable-tbody tr:nth-child(even)) {
  background: var(--ms3-bg-muted);
}

:deep(.p-datatable-tbody tr:hover) {
  background: var(--ms3-bg-neutral);
}

label.required::after {
  content: ' *';
  color: var(--ms3-text-danger-alt);
}

small.text-muted {
  display: block;
  margin-top: 0.25rem;
  color: var(--ms3-text-muted);
  font-size: 0.875rem;
}

label.cursor-pointer {
  cursor: pointer;
  user-select: none;
}

label.opacity-50 {
  opacity: 0.5;
  cursor: not-allowed;
}

.font-mono {
  font-family: monospace;
}

.inline-edit-hint {
  margin: -0.5rem 0 1rem;
  padding: 0.5rem 0.75rem;
  background: var(--ms3-bg-muted, #f0f4f8);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  color: var(--ms3-text-muted, #64748b);
  max-width: 100%;
  overflow-wrap: break-word;
}
</style>
